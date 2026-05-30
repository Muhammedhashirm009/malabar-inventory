Add-Type -AssemblyName System.Drawing

function Convert-To-Squircle-Png ($srcPath, $destPath) {
    Write-Output "Processing $srcPath -> $destPath"
    
    # Load source image
    $srcBitmap = [System.Drawing.Bitmap]::FromFile($srcPath)
    $width = $srcBitmap.Width
    $height = $srcBitmap.Height
    
    # Create destination bitmap with transparency
    $destBitmap = New-Object System.Drawing.Bitmap($width, $height)
    $g = [System.Drawing.Graphics]::FromImage($destBitmap)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    
    # Clear with transparent background
    $g.Clear([System.Drawing.Color]::Transparent)
    
    # Define rounded corners path (squircle shape)
    # The radius is roughly 18% of the width
    $radius = [int]($width * 0.18)
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $rect = New-Object System.Drawing.Rectangle(0, 0, $width, $height)
    
    $path.AddArc(0, 0, $radius*2, $radius*2, 180, 90)
    $path.AddArc(($width - $radius*2), 0, $radius*2, $radius*2, 270, 90)
    $path.AddArc(($width - $radius*2), ($height - $radius*2), $radius*2, $radius*2, 0, 90)
    $path.AddArc(0, ($height - $radius*2), $radius*2, $radius*2, 90, 90)
    $path.CloseAllFigures()
    
    # Clip graphics drawing region to the squircle
    $g.SetClip($path)
    
    # Draw original image within the clip
    $g.DrawImage($srcBitmap, $rect)
    
    # Save the transparency-clipped bitmap
    $destBitmap.Save($destPath, [System.Drawing.Imaging.ImageFormat]::Png)
    
    # Clean up memory
    $g.Dispose()
    $destBitmap.Dispose()
    $srcBitmap.Dispose()
    
    Write-Output "Successfully saved squircle PNG to $destPath"
}

# 1. Convert latest textless JPG (media__1780165149788.jpg) to brand_logo.png (clip white corners)
Convert-To-Squircle-Png -srcPath "C:\Users\Hashir M\.gemini\antigravity\brain\bcaea67f-f521-4b7e-b461-55bf6f4b45be\media__1780165149788.jpg" -destPath "c:\Users\Hashir M\Documents\works\malabar-inventory\public\brand_logo.png"

# 2. Convert latest textless JPG (media__1780165149788.jpg) to logo.png (clip white corners)
Convert-To-Squircle-Png -srcPath "C:\Users\Hashir M\.gemini\antigravity\brain\bcaea67f-f521-4b7e-b461-55bf6f4b45be\media__1780165149788.jpg" -destPath "c:\Users\Hashir M\Documents\works\malabar-inventory\public\logo.png"
