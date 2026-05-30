import os
import urllib.request
import re

# Base paths
BASE_DIR = r"c:\Users\Hashir M\Documents\works\malabar-inventory"
PUBLIC_DIR = os.path.join(BASE_DIR, "public")
JS_DIR = os.path.join(PUBLIC_DIR, "js")
FONTS_DIR = os.path.join(PUBLIC_DIR, "fonts")
INTER_DIR = os.path.join(FONTS_DIR, "inter")
OUTFIT_DIR = os.path.join(FONTS_DIR, "outfit")

os.makedirs(JS_DIR, exist_ok=True)
os.makedirs(INTER_DIR, exist_ok=True)
os.makedirs(OUTFIT_DIR, exist_ok=True)

# User-Agent to get WOFF2 files from Google Fonts (standard Chrome)
USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'

def download_file(url, dest_path):
    print(f"Downloading {url} to {dest_path}...")
    req = urllib.request.Request(url, headers={'User-Agent': USER_AGENT})
    with urllib.request.urlopen(req) as response:
        with open(dest_path, 'wb') as out_file:
            out_file.write(response.read())

def fetch_css(url):
    print(f"Fetching CSS from {url}...")
    req = urllib.request.Request(url, headers={'User-Agent': USER_AGENT})
    with urllib.request.urlopen(req) as response:
        return response.read().decode('utf-8')

# Download JS Assets
js_assets = {
    "https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js": os.path.join(JS_DIR, "alpine.min.js"),
    "https://cdn.jsdelivr.net/npm/chart.js": os.path.join(JS_DIR, "chart.js"),
    "https://unpkg.com/lucide@latest": os.path.join(JS_DIR, "lucide.min.js")
}

for url, path in js_assets.items():
    try:
        download_file(url, path)
    except Exception as e:
        print(f"Error downloading {url}: {e}")

# Process Fonts
fonts_config = {
    "Inter": {
        "url": "https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap",
        "dir": INTER_DIR,
        "prefix": "inter"
    },
    "Outfit": {
        "url": "https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&display=swap",
        "dir": OUTFIT_DIR,
        "prefix": "outfit"
    }
}

fonts_css_content = []

for font_name, cfg in fonts_config.items():
    css_text = fetch_css(cfg["url"])
    # Find all url(...) in the CSS
    urls = re.findall(r'url\((https://[^)]+)\)', css_text)
    
    # We want to download each unique url, name it, and replace in CSS
    url_to_local = {}
    for idx, url in enumerate(sorted(list(set(urls)))):
        file_ext = ".woff2"
        filename = f"{cfg['prefix']}_{idx}{file_ext}"
        local_path = os.path.join(cfg["dir"], filename)
        
        try:
            download_file(url, local_path)
            # Local URL in public/fonts/fonts.css is relative to it
            if cfg["prefix"] == "inter":
                url_to_local[url] = f"inter/{filename}"
            else:
                url_to_local[url] = f"outfit/{filename}"
        except Exception as e:
            print(f"Error downloading font file {url}: {e}")
            
    # Now replace remote URLs in the CSS text
    for remote_url, local_rel_url in url_to_local.items():
        css_text = css_text.replace(remote_url, local_rel_url)
        
    fonts_css_content.append(f"/* ===== {font_name} Font ===== */\n" + css_text)

# Write public/fonts/fonts.css
fonts_css_path = os.path.join(FONTS_DIR, "fonts.css")
with open(fonts_css_path, "w", encoding="utf-8") as f:
    f.write("\n\n".join(fonts_css_content))
print(f"Fonts CSS successfully written to {fonts_css_path}")
