<?php

use Siagraph\Utils\Formatter;

if (!function_exists('render_country_table')) {
    function render_country_table(array $countries, int $pageSize = 20, string $tableId = 'country-table'): void {
        $safeId = htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8');
        echo '<table id="' . $safeId . '" class="table table-dark table-clean text-white w-full border-collapse border border-gray-300">';
        echo '<thead class="bg-gray-800">';
        echo '<tr>';
        echo '<th class="px-4 py-2 border border-gray-300">Country</th>';
        echo '<th class="px-4 py-2 border border-gray-300">Hosts</th>';
        echo '<th class="px-4 py-2 border border-gray-300">Used Storage</th>';
        echo '<th class="px-4 py-2 border border-gray-300">Total Storage</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $index = 0;
        $totalRows = count($countries);
        foreach ($countries as $country => $data) {
            $rowClass = $index % 2 === 0 ? 'bg-gray-900' : 'bg-gray-800';
            // Initially, show only first page; hide others with a class
            $hiddenAttr = ($index >= $pageSize) ? 'style="display:none" class="paged-row ' . $rowClass . '"' : 'class="paged-row ' . $rowClass . '"';
            $safeCountry = htmlspecialchars((string)$country, ENT_QUOTES, 'UTF-8');
            $hostCount = (int)($data['host_count'] ?? 0);
            $used = (int)($data['used_storage'] ?? 0);
            $total = (int)($data['total_storage'] ?? 0);
            echo "<tr $hiddenAttr>";
            echo "<td class=\"px-4 py-2 border border-gray-300 text-center\">$safeCountry</td>";
            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">{$hostCount}</td>";
            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">" . Formatter::formatBytes($used) . "</td>";
            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">" . Formatter::formatBytes($total) . "</td>";
            echo '</tr>';
            $index++;
        }

        echo '</tbody>';
        echo '</table>';
        if ($totalRows > $pageSize) {
            echo '<div class="mt-2 flex items-center justify-between">';
            echo '<div class="text-sm text-gray-400">Showing <span id="' . $safeId . '-from">1</span>–<span id="' . $safeId . '-to">' . $pageSize . '</span> of <span id="' . $safeId . '-total">' . $totalRows . '</span></div>';
            echo '<div class="space-x-2">';
            echo '<button type="button" class="button" id="' . $safeId . '-prev">Prev</button>';
            echo '<button type="button" class="button" id="' . $safeId . '-next">Next</button>';
            echo '</div>';
            echo '</div>';
            echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var tableId="' . $safeId . '";var pageSize=' . (int)$pageSize . ';var rows=Array.prototype.slice.call(document.querySelectorAll("#"+tableId+" tbody tr.paged-row"));var total=rows.length;var totalPages=Math.ceil(total/pageSize);var page=1;var prev=document.getElementById(tableId+"-prev");var next=document.getElementById(tableId+"-next");var fromEl=document.getElementById(tableId+"-from");var toEl=document.getElementById(tableId+"-to");function render(){var start=(page-1)*pageSize;var end=Math.min(start+pageSize,total);rows.forEach(function(r,i){r.style.display=(i>=start && i<end)?"":"none"});fromEl.textContent= total? (start+1):0;toEl.textContent=end;prev.disabled=(page<=1);next.disabled=(page>=totalPages);}if(prev)prev.addEventListener("click",function(){if(page>1){page--;render();}});if(next)next.addEventListener("click",function(){if(page<totalPages){page++;render();}});render();});})();</script>';
        }
    }
}
