<#
.SYNOPSIS
    Copia tutti i file PHP da una cartella sorgente e sottocartelle in una cartella di destinazione,
    escludendo determinate cartelle.
.DESCRIPTION
    Cerca ricorsivamente i file con estensione .php nella cartella sorgente, saltando le cartelle
    specificate in -ExcludeFolders, e li copia nella cartella destinazione.
.PARAMETER Source
    Percorso della cartella sorgente da cui cercare i file PHP.
.PARAMETER Destination
    Percorso della cartella destinazione in cui copiare i file.
.PARAMETER PreserveStructure
    Se specificato, mantiene la struttura delle sottocartelle nella destinazione.
    Se omesso, tutti i file vengono copiati direttamente nella cartella destinazione
    (eventuali omonimi vengono rinominati con un numero).
.PARAMETER ExcludeFolders
    Array di nomi di cartelle da escludere (es. "vendor", "storage"). Default: @("vendor", "storage")
.EXAMPLE
    .\Copy-PHPFiles.ps1 -Source "C:\xampp\htdocs\windeploy\backend" -Destination "C:\xampp\htdocs\windeploy\filephp"
.EXAMPLE
    .\Copy-PHPFiles.ps1 -Source "C:\progetti" -Destination "D:\backup" -PreserveStructure -ExcludeFolders @("node_modules", "tests")
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$Source,
    [Parameter(Mandatory=$true)]
    [string]$Destination,
    [switch]$PreserveStructure,
    [string[]]$ExcludeFolders = @("vendor", "storage")
)

# Converte i percorsi in percorsi assoluti
try {
    $Source = Resolve-Path $Source -ErrorAction Stop
} catch {
    Write-Error "La cartella sorgente non esiste o non è accessibile: $Source"
    exit 1
}
$Destination = [System.IO.Path]::GetFullPath($Destination)

# Crea la cartella destinazione se non esiste
if (-not (Test-Path $Destination)) {
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    Write-Host "Creata cartella destinazione: $Destination"
}

# Funzione per controllare se un percorso deve essere escluso
function Should-Exclude {
    param([string]$Path)
    foreach ($exclude in $ExcludeFolders) {
        # Controlla se il percorso contiene una cartella con quel nome
        if ($Path -match [regex]::Escape([IO.Path]::DirectorySeparatorChar + $exclude + [IO.Path]::DirectorySeparatorChar) -or 
            $Path -match [regex]::Escape($exclude + [IO.Path]::DirectorySeparatorChar) -or
            $Path -eq $exclude) {
            return $true
        }
    }
    return $false
}

# Ottiene tutti i file .php in modo ricorsivo, saltando le cartelle escluse
$files = Get-ChildItem -Path $Source -Filter *.php -Recurse -File | Where-Object {
    -not (Should-Exclude $_.Directory.FullName)
}

if ($files.Count -eq 0) {
    Write-Host "Nessun file PHP trovato (dopo le esclusioni) in $Source"
    exit 0
}

Write-Host "Trovati $($files.Count) file PHP (escluse cartelle: $($ExcludeFolders -join ', ')). Inizio copia..."

$copiedCount = 0
$errorCount = 0

foreach ($file in $files) {
    if ($PreserveStructure) {
        # Calcola il percorso relativo dalla sorgente al file
        $relativePath = $file.FullName.Substring($Source.Length).TrimStart('\')
        $destFilePath = Join-Path $Destination $relativePath
        $destDir = Split-Path $destFilePath -Parent
        # Crea la sottocartella di destinazione se necessario
        if (-not (Test-Path $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
    } else {
        # Appiattimento: usa solo il nome del file
        $destFilePath = Join-Path $Destination $file.Name
        # Se il file esiste già, aggiunge un numero per evitare sovrascritture
        if (Test-Path $destFilePath) {
            $baseName = $file.BaseName
            $extension = $file.Extension
            $counter = 1
            do {
                $newName = "{0}_{1}{2}" -f $baseName, $counter, $extension
                $destFilePath = Join-Path $Destination $newName
                $counter++
            } while (Test-Path $destFilePath)
        }
    }

    try {
        Copy-Item -Path $file.FullName -Destination $destFilePath -Force
        Write-Host "Copiato: $($file.FullName) -> $destFilePath"
        $copiedCount++
    } catch {
        Write-Error "Errore copia di $($file.FullName): $_"
        $errorCount++
    }
}

Write-Host "Copia completata. $copiedCount file copiati, $errorCount errori."