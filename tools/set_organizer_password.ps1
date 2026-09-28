$secret = Read-Host 'New organizer password (15 to 72 bytes)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    $plainPassword | php (Join-Path $PSScriptRoot 'set_organizer_password.php')
    if ($LASTEXITCODE -ne 0) { throw 'Password configuration failed' }
} finally {
    $plainPassword = $null
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $secret.Dispose()
}
