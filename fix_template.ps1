$file = 'c:\xampp\htdocs\encuestas\templates\encuesta.phtml'
$lines = [System.IO.File]::ReadAllLines($file)

# Find the misplaced block: starts at "// Initial button state for tipo 5"
# and ends at the extra </div></li><?php endforeach; ?> added by last patch
# We want to remove:
#   - lines starting with "// Initial button state" through the line with "});"  +blank + "<?php endforeach; ?>"
#   - the extra </div></li><?php endforeach; ?> lines that follow

$startLine = -1
$endLine = -1
$extraStart = -1
$extraEnd = -1

for ($i = 0; $i -lt $lines.Length; $i++) {
    if ($startLine -lt 0 -and $lines[$i].TrimStart() -eq '// Initial button state for tipo 5') {
        $startLine = $i
    }
}

# Find the last `<?php endforeach; ?>` inside the misplaced block (right after `});`)
if ($startLine -ge 0) {
    for ($i = $startLine; $i -lt $lines.Length; $i++) {
        $t = $lines[$i].TrimStart()
        if ($t -eq '});') {
            # Check next non-empty line
            $j = $i + 1
            while ($j -lt $lines.Length -and [string]::IsNullOrWhiteSpace($lines[$j])) { $j++ }
            if ($j -lt $lines.Length -and $lines[$j].TrimStart() -eq '<?php endforeach; ?>') {
                $endLine = $j  # This is the endforeach we WANT to keep
                break
            }
        }
    }
}

Write-Host "startLine=$startLine endLine=$endLine (0-based)"

if ($startLine -lt 0 -or $endLine -lt 0) {
    Write-Host "Could not find block boundaries, aborting"
    exit 1
}

# Check lines after $endLine for extra </div></li><?php endforeach; ?>
# These should be removed if they are extra closing tags
$nextIdx = $endLine + 1
$toRemove = @($startLine..($endLine - 1))  # Remove from startLine to (endLine-1), keeping endLine

# Now check if after endLine there are extra </div></li><?php endforeach; ?> to remove
# Look for the pattern: </div>, </li>, <?php endforeach; ?> immediately after endLine
$extraLines = @()
$k = $endLine + 1
# Skip blank lines
while ($k -lt $lines.Length -and [string]::IsNullOrWhiteSpace($lines[$k])) { $k++ }
$trimK = $lines[$k].TrimStart()
if ($trimK -eq '</div>') {
    $extraLines += $k
    $k++
    while ($k -lt $lines.Length -and [string]::IsNullOrWhiteSpace($lines[$k])) { $k++ }
    if ($lines[$k].TrimStart() -eq '</li>') {
        $extraLines += $k
        $k++
        while ($k -lt $lines.Length -and [string]::IsNullOrWhiteSpace($lines[$k])) { $k++ }
        if ($lines[$k].TrimStart() -eq '<?php endforeach; ?>') {
            $extraLines += $k
        }
    }
}

Write-Host "Extra lines to remove: $extraLines"

# Build new lines array
$removeSet = [System.Collections.Generic.HashSet[int]]($toRemove + $extraLines)
$newLines = [System.Collections.Generic.List[string]]::new()
for ($i = 0; $i -lt $lines.Length; $i++) {
    if (-not $removeSet.Contains($i)) {
        $newLines.Add($lines[$i])
    }
}

[System.IO.File]::WriteAllLines($file, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Done. Removed $($removeSet.Count) lines. New file has $($newLines.Count) lines."
