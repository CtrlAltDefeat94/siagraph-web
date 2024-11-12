<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Page Moved</title>
    <script>
        // Get the current query string from the URL
        var queryString = window.location.search;
        
        // Append the query string to the new URL
        var newUrl = "/host_benchmarks" + queryString;
        
        // Redirect to the new URL with the query parameters
        window.location.replace(newUrl);
    </script>
</head>
</html>
