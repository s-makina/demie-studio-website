<#
Starts the API with the S3 settings from infra/.env exported into its environment.

Spring Boot does not read .env files. Putting S3_ACCESS_KEY / S3_SECRET_KEY in
infra/.env only helps if those variables are present in the environment of the
process that runs the API -- which is why editing .env on its own changes
nothing (S3BucketBootstrap then refuses to start, by design).

Like scripts/upload-fixture.sh this imports the S3_* lines only: infra/.env also
carries DB values that must not override the application.yml defaults.

Usage:
  .\scripts\run-api.ps1
  .\scripts\run-api.ps1 "-Dspring-boot.run.arguments=--server.port=8099"
#>
param([Parameter(ValueFromRemainingArguments = $true)][string[]] $SpringArgs)

$ErrorActionPreference = 'Stop'

$portalRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $portalRoot 'infra\.env'

if (-not (Test-Path $envFile)) {
    throw "Missing $envFile. Copy infra/.env.example and fill in the S3 service-account keys."
}

Get-Content $envFile |
    Where-Object { $_ -match '^S3_' } |
    ForEach-Object {
        $name, $value = $_ -split '=', 2
        $value = $value.Trim()
        Set-Item "Env:$name" $value
        Write-Host ("  {0} = <{1} chars>" -f $name, $value.Length)
    }

foreach ($required in 'S3_ENDPOINT', 'S3_ACCESS_KEY', 'S3_SECRET_KEY') {
    if (-not (Get-Item "Env:$required" -ErrorAction SilentlyContinue)) {
        throw "$required is missing from $envFile (see infra/.env.example)."
    }
}

Push-Location $portalRoot
try {
    & "$portalRoot\mvnw.cmd" spring-boot:run -pl api @SpringArgs
}
finally {
    Pop-Location
}
