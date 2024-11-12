<script>
function formatBytes(bytes) {
    if (bytes < 0) return 'Invalid input'; // Handle negative numbers

    const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    let unitIndex = 0;

    // Convert bytes to the largest unit
    while (bytes >= 1024 && unitIndex < units.length - 1) {
        bytes /= 1024;
        unitIndex++;
    }

    // Round to 3 decimal places
    return `${bytes.toFixed(3)} ${units[unitIndex]}`;
}
</script>