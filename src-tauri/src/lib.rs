use std::process::{Command, Child, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};
use std::thread;
use std::io::Write;
use tauri::{AppHandle, Manager};

static PHP_PROCESS: Mutex<Option<Child>> = Mutex::new(None);

fn clean_path(path: &std::path::Path) -> String {
    let s = path.to_string_lossy();
    if s.starts_with(r"\\?\") {
        s[4..].to_string()
    } else {
        s.into_owned()
    }
}

// ---------------------------------------------------------------------------
// Logging — writes to AppData/logs/startup.log so errors are visible in GUI
// ---------------------------------------------------------------------------

fn log_to_file(app_handle: &AppHandle, message: &str) {
    let timestamp = {
        use std::time::SystemTime;
        match SystemTime::now().duration_since(SystemTime::UNIX_EPOCH) {
            Ok(d) => d.as_secs().to_string(),
            Err(_) => "0".to_string(),
        }
    };

    let log_line = format!("[{}] {}\n", timestamp, message);

    // Always print to stdout/stderr for dev mode
    print!("{}", log_line);

    if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let log_dir = app_data_dir.join("logs");
        let _ = std::fs::create_dir_all(&log_dir);
        let log_file = log_dir.join("startup.log");
        if let Ok(mut f) = std::fs::OpenOptions::new()
            .create(true)
            .append(true)
            .open(&log_file)
        {
            let _ = f.write_all(log_line.as_bytes());
        }
    }
}

// ---------------------------------------------------------------------------
// Resource path resolution — tries multiple strategies for finding bundled files
// ---------------------------------------------------------------------------

/// Find a bundled resource file using multiple resolution strategies.
/// This handles differences between dev mode, MSI installs, and various
/// Tauri v2 resource placement conventions.
fn find_resource_path(app_handle: &AppHandle, relative_path: &str) -> Option<std::path::PathBuf> {
    // Strategy 1: Tauri's built-in resolver (handles _up_ mapping automatically)
    if let Ok(path) = app_handle.path().resolve(relative_path, tauri::path::BaseDirectory::Resource) {
        if path.exists() {
            return Some(path);
        }
    }

    // Strategy 2: _up_ directory convention (Tauri v2 replaces ../ with _up_/ in bundles)
    if let Ok(resource_dir) = app_handle.path().resource_dir() {
        let up_path = relative_path.replace("../", "_up_/");
        let path = resource_dir.join(&up_path);
        if path.exists() {
            return Some(path);
        }
    }

    // Strategy 3: Direct in resource dir (in case resources are flattened)
    if let Ok(resource_dir) = app_handle.path().resource_dir() {
        let stripped = relative_path.trim_start_matches("../");
        let path = resource_dir.join(stripped);
        if path.exists() {
            return Some(path);
        }
    }

    // Strategy 4: Relative to the executable directory
    if let Ok(exe) = std::env::current_exe() {
        if let Some(dir) = exe.parent() {
            let stripped = relative_path.trim_start_matches("../");
            let path = dir.join(stripped);
            if path.exists() {
                return Some(path);
            }
        }
    }

    // Strategy 5: Walk up from current directory (dev mode fallback)
    if let Ok(cwd) = std::env::current_dir() {
        let stripped = relative_path.trim_start_matches("../");
        let mut dir = cwd;
        loop {
            let path = dir.join(stripped);
            if path.exists() {
                return Some(path);
            }
            if !dir.pop() {
                break;
            }
        }
    }

    None
}

// ---------------------------------------------------------------------------
// Laravel root discovery
// ---------------------------------------------------------------------------

fn find_laravel_root(app_handle: &AppHandle) -> Option<std::path::PathBuf> {
    // Strategy 1: Resolve via Tauri resource path (handles _up_ automatically)
    if let Some(artisan_path) = find_resource_path(app_handle, "../artisan") {
        if let Some(parent) = artisan_path.parent() {
            return Some(parent.to_path_buf());
        }
    }

    // Strategy 2: Walk up from executable
    if let Ok(mut path) = std::env::current_exe() {
        while path.pop() {
            if path.join("artisan").exists() {
                return Some(path);
            }
        }
    }

    // Strategy 3: Walk up from current directory
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

// ---------------------------------------------------------------------------
// PHP server management
// ---------------------------------------------------------------------------

fn start_php_server(app_handle: &AppHandle) {
    if is_server_ready() {
        log_to_file(app_handle, "Port 8741 is already in use. Cleaning up stale processes...");
        cleanup_stale_php_processes(app_handle);
    } else {
        log_to_file(app_handle, "Port 8741 is free. Skipping stale process cleanup.");
    }

    let laravel_root = match find_laravel_root(app_handle) {
        Some(path) => path,
        None => {
            log_to_file(app_handle, "ERROR: Could not find Laravel root (artisan not found in any search path)");
            if let Ok(resource_dir) = app_handle.path().resource_dir() {
                log_to_file(app_handle, &format!("  Resource dir: {:?}", resource_dir));
            }
            if let Ok(exe) = std::env::current_exe() {
                log_to_file(app_handle, &format!("  Executable: {:?}", exe));
            }
            return;
        }
    };

    log_to_file(app_handle, &format!("Laravel root found at: {:?}", laravel_root));

    let php_exe_path = match find_resource_path(app_handle, "../php-8.5.1/php.exe") {
        Some(path) => path,
        None => {
            log_to_file(app_handle, "ERROR: Could not find bundled php.exe in any search path");
            log_to_file(app_handle, "  Searched: ../php-8.5.1/php.exe via all resolution strategies");
            if let Ok(resource_dir) = app_handle.path().resource_dir() {
                log_to_file(app_handle, &format!("  Resource dir: {:?}", resource_dir));
                // List what's actually in the resource dir for diagnostics
                if let Ok(entries) = std::fs::read_dir(&resource_dir) {
                    let names: Vec<String> = entries
                        .flatten()
                        .map(|e| e.file_name().to_string_lossy().to_string())
                        .collect();
                    log_to_file(app_handle, &format!("  Resource dir contents: {:?}", names));
                }
            }
            return;
        }
    };

    let php_ini_path = match find_resource_path(app_handle, "../php-8.5.1/php.ini") {
        Some(path) => path,
        None => {
            log_to_file(app_handle, "ERROR: Could not find bundled php.ini in any search path");
            return;
        }
    };

    let php_ini_clean = clean_path(&php_ini_path);
    let laravel_root_clean = clean_path(&laravel_root);

    log_to_file(app_handle, &format!("Using bundled PHP executable at: {:?}", php_exe_path));
    log_to_file(app_handle, &format!("Using bundled PHP ini at: {:?}", php_ini_clean));

    let mut cmd = Command::new(&php_exe_path);
    cmd.arg("-c")
        .arg(&php_ini_clean);

    if let Some(php_dir) = php_exe_path.parent() {
        let ext_dir = php_dir.join("ext");
        cmd.arg("-d")
            .arg(format!("extension_dir={}", clean_path(&ext_dir)));
    }

    cmd.arg("-S")
        .arg("127.0.0.1:8741")
        .arg("-t")
        .arg("public")
        .arg("server.php")
        .current_dir(&laravel_root_clean)
        .stdout(Stdio::piped())
        .stderr(Stdio::piped());

    // Prepend PHP directory to PATH so nested php/artisan commands can resolve php
    if let Some(php_dir) = php_exe_path.parent() {
        if let Some(path_var) = std::env::var_os("PATH") {
            let mut paths = std::env::split_paths(&path_var).collect::<Vec<_>>();
            paths.insert(0, php_dir.to_path_buf());
            if let Ok(new_path) = std::env::join_paths(paths) {
                cmd.env("PATH", new_path);
            }
        } else {
            cmd.env("PATH", php_dir);
        }
    }

    // Set up a writable SQLite database in the user's AppData directory
    let db_path = if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let _ = std::fs::create_dir_all(&app_data_dir);
        let target_db = app_data_dir.join("database.sqlite");

        // If the database does not exist in AppData, copy the seed from resources
        if !target_db.exists() {
            let bundled_db = laravel_root.join("database").join("database.sqlite");
            if bundled_db.exists() {
                match std::fs::copy(&bundled_db, &target_db) {
                    Ok(_) => log_to_file(app_handle, &format!("Copied seed database to: {:?}", target_db)),
                    Err(e) => log_to_file(app_handle, &format!("WARNING: Failed to copy seed database: {}", e)),
                }
            } else {
                let _ = std::fs::write(&target_db, "");
                log_to_file(app_handle, "Created empty database file (no seed found)");
            }
        }
        target_db
    } else {
        laravel_root.join("database").join("database.sqlite")
    };

    log_to_file(app_handle, &format!("Using SQLite database at: {:?}", db_path));
    cmd.env("DB_DATABASE", clean_path(&db_path));

    // Set up a writable storage directory in the user's AppData directory
    if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let storage_path = app_data_dir.join("storage");
        let _ = std::fs::create_dir_all(&storage_path);
        let _ = std::fs::create_dir_all(storage_path.join("app"));
        let _ = std::fs::create_dir_all(storage_path.join("logs"));
        let _ = std::fs::create_dir_all(storage_path.join("framework").join("cache"));
        let _ = std::fs::create_dir_all(storage_path.join("framework").join("sessions"));
        let _ = std::fs::create_dir_all(storage_path.join("framework").join("views"));

        log_to_file(app_handle, &format!("Using AppData storage directory at: {:?}", storage_path));
        cmd.env("APP_STORAGE_PATH", clean_path(&storage_path));
    }

    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        cmd.creation_flags(0x08000000); // CREATE_NO_WINDOW
    }

    match cmd.spawn() {
        Ok(child) => {
            log_to_file(app_handle, &format!("PHP server spawned with PID: {}", child.id()));
            let mut guard = PHP_PROCESS.lock().unwrap();
            *guard = Some(child);
        }
        Err(e) => {
            log_to_file(app_handle, &format!("ERROR: Failed to spawn PHP server: {}", e));
            log_to_file(app_handle, &format!("  Command: {:?}", php_exe_path));
            log_to_file(app_handle, &format!("  Working dir: {:?}", laravel_root));
        }
    }
}

fn cleanup_stale_php_processes(app_handle: &AppHandle) {
    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        log_to_file(app_handle, "Searching for process using port 8741...");

        let output = Command::new("cmd")
            .arg("/c")
            .arg("netstat -ano | findstr :8741")
            .creation_flags(0x08000000) // CREATE_NO_WINDOW
            .output();

        if let Ok(out) = output {
            let stdout = String::from_utf8_lossy(&out.stdout);
            for line in stdout.lines() {
                let parts: Vec<&str> = line.split_whitespace().collect();
                if parts.len() >= 5 {
                    let pid_str = parts[parts.len() - 1];
                    if let Ok(pid) = pid_str.parse::<u32>() {
                        log_to_file(app_handle, &format!("Killing stale process PID: {}", pid));
                        let _ = Command::new("taskkill")
                            .arg("/F")
                            .arg("/PID")
                            .arg(pid.to_string())
                            .creation_flags(0x08000000) // CREATE_NO_WINDOW
                            .status();
                    }
                }
            }
        }
    }

    #[cfg(not(target_os = "windows"))]
    {
        let _ = app_handle; // suppress unused warning
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
    use std::net::{TcpStream, SocketAddr};
    if let Ok(addr) = "127.0.0.1:8741".parse::<SocketAddr>() {
        TcpStream::connect_timeout(&addr, Duration::from_millis(150)).is_ok()
    } else {
        false
    }
}

// ---------------------------------------------------------------------------
// Migrations — run on startup to keep schema up to date after app updates
// ---------------------------------------------------------------------------

fn run_migrations(app_handle: &AppHandle) {
    log_to_file(app_handle, "Checking if database migrations need to run...");

    let php_exe = match find_resource_path(app_handle, "../php-8.5.1/php.exe") {
        Some(p) => p,
        None => {
            log_to_file(app_handle, "WARNING: Cannot run migrations — php.exe not found");
            return;
        }
    };

    let php_ini = match find_resource_path(app_handle, "../php-8.5.1/php.ini") {
        Some(p) => p,
        None => {
            log_to_file(app_handle, "WARNING: Cannot run migrations — php.ini not found");
            return;
        }
    };

    let laravel_root = match find_laravel_root(app_handle) {
        Some(p) => p,
        None => {
            log_to_file(app_handle, "WARNING: Cannot run migrations — Laravel root not found");
            return;
        }
    };

    let php_ini_clean = clean_path(&php_ini);
    let laravel_root_clean = clean_path(&laravel_root);

    // Build the migration command
    let mut cmd = Command::new(&php_exe);
    cmd.arg("-c")
        .arg(&php_ini_clean);

    if let Some(php_dir) = php_exe.parent() {
        let ext_dir = php_dir.join("ext");
        cmd.arg("-d")
            .arg(format!("extension_dir={}", clean_path(&ext_dir)));
    }

    cmd.arg("artisan")
        .arg("migrate")
        .arg("--force")
        .current_dir(&laravel_root_clean);

    // Set the same environment variables as the PHP server
    if let Some(php_dir) = php_exe.parent() {
        if let Some(path_var) = std::env::var_os("PATH") {
            let mut paths = std::env::split_paths(&path_var).collect::<Vec<_>>();
            paths.insert(0, php_dir.to_path_buf());
            if let Ok(new_path) = std::env::join_paths(paths) {
                cmd.env("PATH", new_path);
            }
        } else {
            cmd.env("PATH", php_dir);
        }
    }

    // Set DB path
    if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let target_db = app_data_dir.join("database.sqlite");
        cmd.env("DB_DATABASE", clean_path(&target_db));
    }

    // Set storage path
    if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let storage_path = app_data_dir.join("storage");
        cmd.env("APP_STORAGE_PATH", clean_path(&storage_path));
    }

    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        cmd.creation_flags(0x08000000); // CREATE_NO_WINDOW
    }

    match cmd.output() {
        Ok(output) => {
            let stdout = String::from_utf8_lossy(&output.stdout);
            let stderr = String::from_utf8_lossy(&output.stderr);
            if output.status.success() {
                log_to_file(app_handle, &format!("Migrations completed: {}", stdout.trim()));
            } else {
                log_to_file(app_handle, &format!("WARNING: Migration command failed (exit code: {:?})", output.status.code()));
                if !stdout.is_empty() {
                    log_to_file(app_handle, &format!("  stdout: {}", stdout.trim()));
                }
                if !stderr.is_empty() {
                    log_to_file(app_handle, &format!("  stderr: {}", stderr.trim()));
                }
            }
        }
        Err(e) => {
            log_to_file(app_handle, &format!("WARNING: Failed to execute migration command: {}", e));
        }
    }
}

// ---------------------------------------------------------------------------
// Splash → main window transition
// ---------------------------------------------------------------------------

fn wait_and_transition(app_handle: &AppHandle) {
    let start_time = Instant::now();
    let min_duration = Duration::from_secs(3);

    let mut ready = false;
    for i in 0..100 {
        if is_server_ready() {
            ready = true;
            log_to_file(app_handle, &format!("PHP server is ready (after {}ms)", i * 50));
            break;
        }
        thread::sleep(Duration::from_millis(50));
    }

    if !ready {
        log_to_file(app_handle, "ERROR: PHP server did not become ready within 5 seconds");

        // Check if PHP process is still alive
        let mut guard = PHP_PROCESS.lock().unwrap();
        if let Some(ref mut child) = *guard {
            match child.try_wait() {
                Ok(Some(status)) => {
                    log_to_file(app_handle, &format!("  PHP process exited with status: {}", status));
                    // Try to read stderr for error details
                    if let Some(mut stderr) = child.stderr.take() {
                        let mut buf = String::new();
                        if std::io::Read::read_to_string(&mut stderr, &mut buf).is_ok() && !buf.is_empty() {
                            log_to_file(app_handle, &format!("  PHP stderr: {}", buf.trim()));
                        }
                    }
                }
                Ok(None) => log_to_file(app_handle, "  PHP process is still running but not accepting connections"),
                Err(e) => log_to_file(app_handle, &format!("  Error checking PHP process status: {}", e)),
            }
        } else {
            log_to_file(app_handle, "  No PHP process was spawned");
        }
    }

    // Ensure minimum splash display time for visual polish
    let elapsed = start_time.elapsed();
    if elapsed < min_duration {
        thread::sleep(min_duration - elapsed);
    }

    // Always show main window and close splash, even if PHP failed
    if let Some(main_window) = app_handle.get_webview_window("main") {
        let _ = main_window.show();
        let _ = main_window.set_focus();
        log_to_file(app_handle, "Main window shown");
    } else {
        log_to_file(app_handle, "ERROR: Could not find main window");
    }
    if let Some(splash_window) = app_handle.get_webview_window("splashscreen") {
        let _ = splash_window.close();
        log_to_file(app_handle, "Splashscreen closed");
    }
}

// ---------------------------------------------------------------------------
// Backup system
// ---------------------------------------------------------------------------

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
async fn backup_database(app_handle: tauri::AppHandle) -> Result<String, String> {
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

    let db_path = if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let target_db = app_data_dir.join("database.sqlite");
        if target_db.exists() {
            target_db
        } else {
            let laravel_root = match find_laravel_root(&app_handle) {
                Some(path) => path,
                None => return Err("Could not find Laravel root".to_string()),
            };
            laravel_root.join("database").join("database.sqlite")
        }
    } else {
        let laravel_root = match find_laravel_root(&app_handle) {
            Some(path) => path,
            None => return Err("Could not find Laravel root".to_string()),
        };
        laravel_root.join("database").join("database.sqlite")
    };

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

fn get_backup_config_path(app_handle: &AppHandle, laravel_root: &std::path::Path) -> std::path::PathBuf {
    if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        app_data_dir.join("storage").join("app").join("backup_directory.txt")
    } else {
        laravel_root.join("storage").join("app").join("backup_directory.txt")
    }
}

#[tauri::command]
async fn set_backup_directory(app_handle: tauri::AppHandle) -> Result<String, String> {
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

    let laravel_root = match find_laravel_root(&app_handle) {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_path = get_backup_config_path(&app_handle, &laravel_root);
    if let Some(config_dir) = config_path.parent() {
        if !config_dir.exists() {
            let _ = std::fs::create_dir_all(&config_dir);
        }
    }

    if let Err(e) = std::fs::write(&config_path, &selected_path) {
        return Err(format!("Failed to save backup configuration: {}", e));
    }

    Ok(selected_path)
}

#[tauri::command]
async fn get_backup_directory(app_handle: tauri::AppHandle) -> Result<String, String> {
    let laravel_root = match find_laravel_root(&app_handle) {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_path = get_backup_config_path(&app_handle, &laravel_root);
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
async fn clear_backup_directory(app_handle: tauri::AppHandle) -> Result<(), String> {
    let laravel_root = match find_laravel_root(&app_handle) {
        Some(path) => path,
        None => return Err("Could not find Laravel root".to_string()),
    };

    let config_path = get_backup_config_path(&app_handle, &laravel_root);
    if config_path.exists() {
        let _ = std::fs::remove_file(config_path);
    }
    Ok(())
}

fn perform_exit_backup(app_handle: &tauri::AppHandle) {
    let laravel_root = match find_laravel_root(app_handle) {
        Some(path) => path,
        None => return,
    };

    let config_path = get_backup_config_path(app_handle, &laravel_root);
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

    let db_path = if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
        let target_db = app_data_dir.join("database.sqlite");
        if target_db.exists() {
            target_db
        } else {
            laravel_root.join("database").join("database.sqlite")
        }
    } else {
        laravel_root.join("database").join("database.sqlite")
    };

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

// ---------------------------------------------------------------------------
// Application entry point
// ---------------------------------------------------------------------------

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

            // Log startup info
            log_to_file(&app_handle, "=== Lamya Pro starting ===");
            if let Ok(resource_dir) = app_handle.path().resource_dir() {
                log_to_file(&app_handle, &format!("Resource directory: {:?}", resource_dir));
            }
            if let Ok(app_data_dir) = app_handle.path().app_data_dir() {
                log_to_file(&app_handle, &format!("AppData directory: {:?}", app_data_dir));
            }
            if let Ok(exe) = std::env::current_exe() {
                log_to_file(&app_handle, &format!("Executable: {:?}", exe));
            }

            let app_handle_for_thread = app_handle.clone();

            std::thread::spawn(move || {
                // Spawn PHP server
                let server_result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
                    start_php_server(&app_handle_for_thread);
                }));

                if let Err(panic_info) = &server_result {
                    // Log the panic
                    let msg = if let Some(s) = panic_info.downcast_ref::<&str>() {
                        format!("PANIC during server start: {}", s)
                    } else if let Some(s) = panic_info.downcast_ref::<String>() {
                        format!("PANIC during server start: {}", s)
                    } else {
                        "PANIC during server start (unknown payload)".to_string()
                    };
                    log_to_file(&app_handle_for_thread, &msg);
                }

                // ALWAYS transition from splash to main, even if everything failed
                wait_and_transition(&app_handle_for_thread);

                // Run migrations in the background so it doesn't block the UI load
                let app_handle_for_migrations = app_handle_for_thread.clone();
                std::thread::spawn(move || {
                    let migration_result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
                        run_migrations(&app_handle_for_migrations);
                    }));

                    if let Err(panic_info) = &migration_result {
                        let msg = if let Some(s) = panic_info.downcast_ref::<&str>() {
                            format!("PANIC during migrations: {}", s)
                        } else if let Some(s) = panic_info.downcast_ref::<String>() {
                            format!("PANIC during migrations: {}", s)
                        } else {
                            "PANIC during migrations (unknown payload)".to_string()
                        };
                        log_to_file(&app_handle_for_migrations, &msg);
                    }
                });
            });

            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("error while building tauri application")
        .run(|app_handle, event| {
            if let tauri::RunEvent::Exit = event {
                perform_exit_backup(app_handle);
                kill_php_server();
            }
        });
}
