# Resize + re-encode the Figma PNG exports as web JPEGs before upload.
# Industry cards render 348px wide (2x = ~700px), so 1024px wide is ample;
# the Heart photo renders ~700px wide, so 1400px covers 2x.
Add-Type -AssemblyName System.Drawing
$src = 'C:\Sample Code\refs\assets\home'
$dst = Join-Path $src 'upload'
$jobs = @(
  @('ind-government.png',    'ap-industry-government.jpg',    1024),
  @('ind-manufacturing.png', 'ap-industry-manufacturing.jpg', 1024),
  @('ind-healthcare.png',    'ap-industry-healthcare.jpg',    1024),
  @('ind-education-b.png',   'ap-industry-education.jpg',     1024),
  @('ind-construction.png',  'ap-industry-construction.jpg',  1024),
  @('ind-nonprofit-b.png',   'ap-industry-nonprofit.jpg',     1024),
  @('heart-photo.png',       'ap-heart-team.jpg',             1400)
)
$enc = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
$par = New-Object System.Drawing.Imaging.EncoderParameters 1
$par.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter ([System.Drawing.Imaging.Encoder]::Quality), ([long]82)
foreach ($j in $jobs) {
  $img = [System.Drawing.Image]::FromFile((Join-Path $src $j[0]))
  $w = [Math]::Min([int]$j[2], $img.Width)
  $h = [int][Math]::Round($img.Height * $w / $img.Width)
  $bmp = New-Object System.Drawing.Bitmap $w, $h
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.DrawImage($img, 0, 0, $w, $h)
  $out = Join-Path $dst $j[1]
  $bmp.Save($out, $enc, $par)
  $g.Dispose(); $bmp.Dispose(); $img.Dispose()
  '{0,-32} {1}x{2}  {3} KB' -f $j[1], $w, $h, [int]((Get-Item $out).Length / 1KB)
}
