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
    var mode = getCookie('mode');
    var newMode = (mode === 'dark') ? 'light' : 'dark';
    document.cookie = "mode=" + newMode + "; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/";
    applyModeFromCookie();
}

function applyModeFromCookie() {
    const mode = getCookie('mode');
    if (mode === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
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


// Function to get the value of a cookie
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return match[2];
}

function getLocalizedTime(timestamp) {
    // Create a new Date object from the provided UTC timestamp
    const date = new Date(timestamp);
    // Format the date using the local time zone (no need for manual adjustment)
    const localizedTime = date.toLocaleString({
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
