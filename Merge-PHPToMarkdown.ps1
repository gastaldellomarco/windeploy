<#
.SYNOPSIS
    Unisce tutti i file PHP da una cartella sorgente in un unico file Markdown.
.DESCRIPTION
    Cerca ricorsivamente i file .php nella cartella Source (saltando le cartelle specificate
    in ExcludeFolders) e crea un unico file .md contenente il nome e il contenuto di ciascun file.
.PARAMETER Source
    Percorso della cartella sorgente.
.PARAMETER OutputFile
    Percorso del file Markdown di output.
.PARAMETER ExcludeFolders
    Array di nomi di cartelle da escludere (default: vendor, storage).
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$Source,
    [string]$OutputFile,
    [string[]]$ExcludeFolders = @('vendor', 'storage')
)

# Se OutputFile non è specificato, usa un nome predefinito nella cartella corrente
if (-not $OutputFile) {
    $OutputFile = Join-Path (Get-Location) 'merged.md'
}

# Verifica che la cartella sorgente esista
if (-not (Test-Path $Source -PathType Container)) {
    Write-Error "La cartella sorgente non esiste: $Source"
    exit 1
}

# Crea la directory di output se non esiste
$outputDir = Split-Path $OutputFile -Parent
if ($outputDir -and -not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
    Write-Host "Creata cartella di output: $outputDir"
}

# Costruisce l'array di esclusioni per Get-ChildItem (formato cartelle da escludere)
# Get-ChildItem con -Exclude funziona solo sul nome, non sul percorso completo.
# Quindi dobbiamo fare una ricerca ricorsiva e poi filtrare.
Write-Host "Ricerca file PHP in $Source ..."
$allFiles = Get-ChildItem -Path $Source -Filter *.php -Recurse -File

# Filtra escludendo i file che si trovano in una delle cartelle escluse
$files = $allFiles | Where-Object {
    $exclude = $false
    foreach ($folder in $ExcludeFolders) {
        # Controlla se il percorso contiene la cartella esclusa
        if ($_.FullName -match [regex]::Escape("\$folder") -or $_.FullName -match [regex]::Escape("/$folder")) {
            $exclude = $true
            break
        }
    }
    -not $exclude
}

if ($files.Count -eq 0) {
    Write-Host "Nessun file PHP trovato (dopo le esclusioni)."
    exit 0
}

Write-Host "Trovati $($files.Count) file PHP. Creazione del file Markdown..."

# Prepara l'intestazione del file Markdown
$markdownLines = @()
$markdownLines += '# File PHP unificati'
$markdownLines += ''
$markdownLines += "Data: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$markdownLines += ''
$markdownLines += '---'
$markdownLines += ''

$processedCount = 0
$errorCount = 0

foreach ($file in $files) {
    # Percorso relativo dalla cartella sorgente
    $relativePath = $file.FullName.Substring($Source.Length).TrimStart('\', '/')
    
    $markdownLines += "## $relativePath"
    $markdownLines += ''
    $markdownLines += '```php'
    
    try {
        $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
        $markdownLines += $content.TrimEnd()
        $processedCount++
    } catch {
        $markdownLines += "**ERRORE LETTURA FILE:** $($_.Exception.Message)"
        $errorCount++
    }
    
    $markdownLines += '```'
    $markdownLines += ''
}

# Scrive il file
try {
    $markdownLines -join "`r`n" | Set-Content -Path $OutputFile -Encoding UTF8 -NoNewline
    Write-Host "File Markdown creato con successo: $OutputFile"
    Write-Host "Processati: $processedCount file, Errori: $errorCount"
} catch {
    Write-Error "Errore durante la scrittura del file di output: $($_.Exception.Message)"
    exit 1
}