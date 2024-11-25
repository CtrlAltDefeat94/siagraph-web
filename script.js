// script.js
function displayStorageMetric(id, actualValue, changeValue) {
    const actualElement = document.getElementById(id + 'a');
    const changeElement = document.getElementById(id + 'b');

    if (actualElement && changeElement) {
        actualElement.textContent = actualValue;
        changeElement.textContent = (changeValue > 0) ? ` (+${changeValue})` : ` (${changeValue})`;
    } else {
        console.error('Elements with id ' + id + 'a or ' + id + 'b not found.');
    }
}



    function switchLightDarkMode() {
        // Check if the 'mode' cookie exists
        var modeCookie = getCookie('mode');

        // If the 'mode' cookie doesn't exist, set it to 'dark'
        // Otherwise, toggle between 'light' and 'dark'
        var newMode = (modeCookie === 'light') ? 'dark' : 'light';

        // Set the 'mode' cookie with the new value
        document.cookie = "mode=" + newMode + "; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/";

        // Refresh the page
        location.reload();
    }

function switchEuroDollar() {
    // Check if the 'currency' cookie exists
    var currencyCookie = getCookie('currency');

    // If the 'currency' cookie doesn't exist, set it to 'eur'
    // Otherwise, toggle between 'eur' and 'usd'
    var newCurrency = (currencyCookie === 'usd' || currencyCookie === null) ? 'eur' : 'usd';

    // Set the 'currency' cookie with the new value
    document.cookie = "currency=" + newCurrency + "; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/";

    // Refresh the page
    location.reload();
}

    // Helper function to get the value of a cookie by name
    function getCookie(name) {
        var cookieArr = document.cookie.split('; ');
        for (var i = 0; i < cookieArr.length; i++) {
            var cookiePair = cookieArr[i].split('=');
            if (cookiePair[0] === name) {
                return cookiePair[1];
            }
        }
        return null;
    }


// Function to apply light mode
function applyLightMode() {
    document.getElementById("theme-style").href = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css";
    document.getElementById("header").classList.remove("bg-dark");
}

// Function to apply dark mode
function applyDarkMode() {
    document.getElementById("theme-style").href = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.dark.min.css"; // Bootstrap dark theme
    document.getElementById("header").classList.add("bg-dark");
}

// Function to get the value of a cookie
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return match[2];
}

function getLocalizedTime(timestamp) {
    // Create a new Date object from the provided UTC timestamp
    const date = new Date(timestamp);
    // Format the date using the local time zone (no need for manual adjustment)
    const localizedTime = date.toLocaleString( {
        timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone, // Get the local timezone dynamically
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

    return localizedTime;
}
