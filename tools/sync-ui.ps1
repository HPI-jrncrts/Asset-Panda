#requires -Version 5.1
<#
.SYNOPSIS
    Copies the shared UI kit (ui/) into each vertical's lib/asset-panda-ui/.

.DESCRIPTION
    ui/ is the single source of truth for shared markup, CSS, and the loader.
    Neither vertical imports from ui/ at runtime; instead this script mirrors the
    whole ui/ tree into:

        gutenberg/lib/asset-panda-ui/
        unyson/lib/asset-panda-ui/

    Run this after any change under ui/. The destination copies are generated —
    never edit them by hand.

.EXAMPLE
    pwsh tools/sync-ui.ps1
#>

[CmdletBinding()]
param(
    # Preview what would be copied without writing anything.
    [switch] $WhatIf
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$source   = Join-Path $repoRoot 'ui'

if (-not (Test-Path -LiteralPath $source)) {
    throw "Source kit not found at '$source'."
}

# Each vertical receives a full copy under lib/asset-panda-ui/.
$targets = @(
    (Join-Path $repoRoot 'gutenberg/lib/asset-panda-ui'),
    (Join-Path $repoRoot 'unyson/lib/asset-panda-ui')
)

# Files/dirs in ui/ that should NOT ship to the verticals.
$exclude = @('CLAUDE.md')

foreach ($target in $targets) {
    Write-Host "Syncing ui/ -> $target"

    if ($WhatIf) {
        Write-Host "  (WhatIf) would mirror $source to $target, excluding: $($exclude -join ', ')"
        continue
    }

    # Start clean so deletions in ui/ propagate.
    if (Test-Path -LiteralPath $target) {
        Remove-Item -LiteralPath $target -Recurse -Force
    }
    New-Item -ItemType Directory -Path $target -Force | Out-Null

    Get-ChildItem -LiteralPath $source -Recurse -File | ForEach-Object {
        $relative = $_.FullName.Substring($source.Length).TrimStart('\', '/')

        # Skip excluded top-level entries (e.g. the kit's own CLAUDE.md).
        $top = ($relative -split '[\\/]')[0]
        if ($exclude -contains $top) { return }

        $dest    = Join-Path $target $relative
        $destDir = Split-Path -Parent $dest
        if (-not (Test-Path -LiteralPath $destDir)) {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null
        }
        Copy-Item -LiteralPath $_.FullName -Destination $dest -Force
    }

    Write-Host "  done."
}

Write-Host "Sync complete."
