Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
if ([System.Windows.Forms.Clipboard]::ContainsImage()) {
    $img = [System.Windows.Forms.Clipboard]::GetImage()
    $img.Save('C:\laragon\www\siupt-refeicoes\public\assets\img\icone-peixe.png', [System.Drawing.Imaging.ImageFormat]::Png)
    Write-Host 'Saved successfully'
} else {
    Write-Host 'No Image in Clipboard'
}
