$Plugin = "credit-packs-for-woocommerce"
$Version = "1.0.0"

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Dist = Join-Path $Root "dist"
$ZipPath = Join-Path $Dist "$Plugin-v$Version.zip"

if (Test-Path $Dist) {
    Remove-Item $Dist -Recurse -Force
}

New-Item -ItemType Directory -Path $Dist | Out-Null

$ExcludeDirs = @(
    ".git",
    ".github",
    ".wordpress-org",
    "dist",
    "build"
)

$ExcludeFiles = @(
    "build-release.bat",
    "build-release.ps1",
    ".gitignore",
    ".gitattributes"
)

Add-Type -AssemblyName System.IO.Compression.FileSystem

$Archive = [System.IO.Compression.ZipFile]::Open($ZipPath, "Create")

Get-ChildItem -Path $Root -Recurse -File | ForEach-Object {
    $Relative = $_.FullName.Substring($Root.Length + 1)

    foreach ($Dir in $ExcludeDirs) {
        if ($Relative -like "$Dir\*" -or $Relative -like "$Dir/*") {
            return
        }
    }

    if ($ExcludeFiles -contains $_.Name) {
        return
    }

    if ($_.Extension -eq ".zip") {
        return
    }

    $ZipEntry = "$Plugin/" + ($Relative -replace "\\", "/")

    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $Archive,
        $_.FullName,
        $ZipEntry
    ) | Out-Null
}

$Archive.Dispose()

Write-Host ""
Write-Host "Created release zip:"
Write-Host $ZipPath