use std::process::{Command, Child, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};
use std::thread;
use tauri::{AppHandle, Manager};

static PHP_PROCESS: Mutex<Option<Child>> = Mutex::new(None);

fn find_laravel_root() -> Option<std::path::PathBuf> {
    if let Ok(mut path) = std::env::current_exe() {
        while path.pop() {
            if path.join("artisan").exists() {
                return Some(path);
            }
        }
    }
    if let Ok(path) = std::env::current_dir() {
        let mut p = path;
        loop {
            if p.join("artisan").exists() {
                return Some(p);
            }
            if !p.pop() {
                break;
            }
        }
    }
    None
}

fn start_php_server() {
    cleanup_stale_php_processes();

    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => {
            eprintln!("Could not find Laravel root (artisan not found)");
            return;
        }
    };

    println!("Laravel root found at: {:?}", laravel_root);

    let mut cmd = Command::new("php");
    cmd.arg("artisan")
        .arg("serve")
        .arg("--port=8741")
        .current_dir(&laravel_root)
        .stdout(Stdio::piped())
        .stderr(Stdio::piped());

    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        cmd.creation_flags(0x08000000);
    }

    match cmd.spawn() {
        Ok(child) => {
            println!("PHP server spawned with PID: {}", child.id());
            let mut guard = PHP_PROCESS.lock().unwrap();
            *guard = Some(child);
        }
        Err(e) => {
            eprintln!("Failed to spawn PHP server: {}", e);
        }
    }
}

fn cleanup_stale_php_processes() {
    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        println!("Cleaning up any stale processes on port 8741...");
        let _ = Command::new("cmd")
            .arg("/c")
            .arg("for /f \"tokens=5\" %a in ('netstat -aon ^| findstr :8741') do taskkill /F /PID %a")
            .creation_flags(0x08000000) // CREATE_NO_WINDOW
            .status();
    }
}

fn kill_php_server() {
    let mut guard = PHP_PROCESS.lock().unwrap();
    if let Some(mut child) = guard.take() {
        let pid = child.id();
        println!("Killing PHP server process tree (PID: {})...", pid);
        
        #[cfg(target_os = "windows")]
        {
            use std::os::windows::process::CommandExt;
            let _ = Command::new("taskkill")
                .arg("/F")
                .arg("/T")
                .arg("/PID")
                .arg(pid.to_string())
                .creation_flags(0x08000000) // CREATE_NO_WINDOW
                .status();
        }
        
        let _ = child.kill();
        let _ = child.wait();
    }
}

fn is_server_ready() -> bool {
    use std::net::TcpStream;
    TcpStream::connect("127.0.0.1:8741").is_ok()
}

fn wait_and_transition(app_handle: AppHandle) {
    let start_time = Instant::now();
    let min_duration = Duration::from_secs(3);

    let mut ready = false;
    for _ in 0..100 {
        if is_server_ready() {
            ready = true;
            break;
        }
        thread::sleep(Duration::from_millis(50));
    }

    if !ready {
        eprintln!("PHP server did not become ready in time.");
    }

    let elapsed = start_time.elapsed();
    if elapsed < min_duration {
        thread::sleep(min_duration - elapsed);
    }

    if let Some(main_window) = app_handle.get_webview_window("main") {
        let _ = main_window.show();
        let _ = main_window.set_focus();
    }
    if let Some(splash_window) = app_handle.get_webview_window("splashscreen") {
        let _ = splash_window.close();
    }
}

fn rotate_backups(dir: &std::path::Path) {
    let entries = match std::fs::read_dir(dir) {
        Ok(e) => e,
        Err(_) => return,
    };

    let mut backup_files = Vec::new();

    for entry in entries.flatten() {
        let path = entry.path();
        if path.is_file() {
            if let Some(filename) = path.file_name().and_then(|f| f.to_str()) {
                // Match both manual and auto backups
                if (filename.starts_with("lamya_backup_") || filename.starts_with("lamya_auto_backup_")) 
                    && filename.ends_with(".sqlite") 
                {
                    backup_files.push(path);
                }
            }
        }
    }

    // Sort alphabetically by filename (timestamp makes this perfectly chronological)
    backup_files.sort_by(|a, b| {
        a.file_name().cmp(&b.file_name())
    });

    // If there are more than 7, delete the oldest
    if backup_files.len() > 7 {
        let delete_count = backup_files.len() - 7;
        for i in 0..delete_count {
            println!("Deleting old backup file: {:?}", backup_files[i]);
            let _ = std::fs::remove_file(&backup_files[i]);
        }
    }
}

#[tauri::command]
async fn backup_database() -> Result<String, String> {
    #[cfg(target_os = "windows")]
    let output = {
        use std::os::windows::process::CommandExt;
        Command::new("powershell")
            .arg("-NoProfile")
            .arg("-Command")
            .arg("$app = New-Object -ComObject Shell.Application; $folder = $app.BrowseForFolder(0, 'Select folder to save database backup', 0, 17); if ($folder) { $folder.Self.Path }")
            .creation_flags(0x08000000)
            .output()
    };

    #[cfg(not(target_os = "windows"))]
    let output = Err(std::io::Error::new(std::io::ErrorKind::Unsupported, "Only supported on Windows"));

    let res = match output {
        Ok(out) => {
            let path_str = String::from_utf8_lossy(&out.stdout).trim().to_string();
            if path_str.is_empty() {
                return Err("No folder selected".to_string());
            }
            std::path::PathBuf::from(path_str)
        }
        Err(e) => {
            return Err(format!("Failed to open directory selection: {}", e));
        }
    };

    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let db_path = laravel_root.join("database").join("database.sqlite");
    if !db_path.exists() {
        return Err("Database file does not exist. Make sure you run migrations first.".to_string());
    }

    use std::time::SystemTime;
    let timestamp = match SystemTime::now().duration_since(SystemTime::UNIX_EPOCH) {
        Ok(n) => n.as_secs(),
        Err(_) => 0,
    };
    
    let date_str = match Command::new("powershell")
        .arg("-NoProfile")
        .arg("-Command")
        .arg("Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'")
        .output() {
            Ok(out) => String::from_utf8_lossy(&out.stdout).trim().to_string(),
            Err(_) => timestamp.to_string(),
        };

    let backup_filename = format!("lamya_backup_{}.sqlite", date_str);
    let destination = res.join(&backup_filename);

    match std::fs::copy(&db_path, &destination) {
        Ok(_) => {
            // Rotate backups in manual folder
            rotate_backups(&res);
            Ok(format!(
                "Database backed up successfully to: {}",
                destination.to_string_lossy()
            ))
        }
        Err(e) => Err(format!("Failed to copy database file: {}", e)),
    }
}

#[tauri::command]
fn minimize_window(window: tauri::Window) {
    let _ = window.minimize();
}

#[tauri::command]
fn close_window(window: tauri::Window) {
    let _ = window.close();
}

#[tauri::command]
async fn set_backup_directory() -> Result<String, String> {
    #[cfg(target_os = "windows")]
    let output = {
        use std::os::windows::process::CommandExt;
        Command::new("powershell")
            .arg("-NoProfile")
            .arg("-Command")
            .arg("$app = New-Object -ComObject Shell.Application; $folder = $app.BrowseForFolder(0, 'Select folder for automatic backups on exit', 0, 17); if ($folder) { $folder.Self.Path }")
            .creation_flags(0x08000000)
            .output()
    };

    #[cfg(not(target_os = "windows"))]
    let output = Err(std::io::Error::new(std::io::ErrorKind::Unsupported, "Only supported on Windows"));

    let selected_path = match output {
        Ok(out) => {
            let path_str = String::from_utf8_lossy(&out.stdout).trim().to_string();
            if path_str.is_empty() {
                return Err("No folder selected".to_string());
            }
            path_str
        }
        Err(e) => {
            return Err(format!("Failed to open directory selection: {}", e));
        }
    };

    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_dir = laravel_root.join("storage").join("app");
    let config_path = config_dir.join("backup_directory.txt");

    if !config_dir.exists() {
        let _ = std::fs::create_dir_all(&config_dir);
    }

    if let Err(e) = std::fs::write(&config_path, &selected_path) {
        return Err(format!("Failed to save backup configuration: {}", e));
    }

    Ok(selected_path)
}

#[tauri::command]
async fn get_backup_directory() -> Result<String, String> {
    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_path = laravel_root.join("storage").join("app").join("backup_directory.txt");
    if config_path.exists() {
        match std::fs::read_to_string(&config_path) {
            Ok(content) => Ok(content.trim().to_string()),
            Err(_) => Ok("".to_string()),
        }
    } else {
        Ok("".to_string())
    }
}

#[tauri::command]
async fn clear_backup_directory() -> Result<(), String> {
    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_path = laravel_root.join("storage").join("app").join("backup_directory.txt");
    if config_path.exists() {
        let _ = std::fs::remove_file(config_path);
    }
    Ok(())
}

fn perform_exit_backup() {
    let laravel_root = match find_laravel_root() {
        Some(path) => path,
        None => return,
    };

    let config_path = laravel_root.join("storage").join("app").join("backup_directory.txt");
    if !config_path.exists() {
        return;
    }

    let backup_dir_str = match std::fs::read_to_string(&config_path) {
        Ok(c) => c.trim().to_string(),
        Err(_) => return,
    };

    if backup_dir_str.is_empty() {
        return;
    }

    let backup_dir = std::path::PathBuf::from(backup_dir_str);
    if !backup_dir.exists() {
        return;
    }

    let db_path = laravel_root.join("database").join("database.sqlite");
    if !db_path.exists() {
        return;
    }

    use std::time::SystemTime;
    let timestamp = match SystemTime::now().duration_since(SystemTime::UNIX_EPOCH) {
        Ok(n) => n.as_secs(),
        Err(_) => 0,
    };

    let date_str = match Command::new("powershell")
        .arg("-NoProfile")
        .arg("-Command")
        .arg("Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'")
        .output() {
            Ok(out) => String::from_utf8_lossy(&out.stdout).trim().to_string(),
            Err(_) => timestamp.to_string(),
        };

    let backup_filename = format!("lamya_auto_backup_{}.sqlite", date_str);
    let destination = backup_dir.join(&backup_filename);

    if std::fs::copy(&db_path, &destination).is_ok() {
        // Rotate backups in automatic folder
        rotate_backups(&backup_dir);
    }
    println!("Database auto-backed up to: {:?}", destination);
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![
            backup_database, 
            minimize_window, 
            close_window,
            set_backup_directory,
            get_backup_directory,
            clear_backup_directory
        ])
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            let app_handle = app.handle().clone();
            std::thread::spawn(move || {
                start_php_server();
                wait_and_transition(app_handle);
            });

            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("error while building tauri application")
        .run(|_app_handle, event| {
            if let tauri::RunEvent::Exit = event {
                perform_exit_backup();
                kill_php_server();
            }
        });
}
