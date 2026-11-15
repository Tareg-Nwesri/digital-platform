# Builds the front end locally and uploads public/build to the server.
# Usage: ./deploy/upload-assets.ps1 -Target "u123456789@123.45.67.89" -Port 65002 -Path "domains/example.ly/digital-platform"
param(
    [Parameter(Mandatory = $true)][string]$Target,
    [int]$Port = 65002,
    [Parameter(Mandatory = $true)][string]$Path
)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

npm ci
npm run build

ssh -p $Port $Target "rm -rf $Path/public/build && mkdir -p $Path/public"
scp -P $Port -r public/build "${Target}:$Path/public/build"
Write-Host "Assets uploaded. Now run on the server: bash $Path/deploy/server-update.sh"
