<?php
$tokenomicspath = '/opt/siagraph/rawdata/tokenomics.json';
$tokenomics = file_get_contents($tokenomicspath);
$data = json_decode($tokenomics, true);
?>

<!-- Import Moment.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>

<!-- Import Chart.js 3 library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<!-- Import Chart.js Moment adapter -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0/dist/chartjs-adapter-moment.min.js"></script>

<!-- Create a canvas element to render the Chart.js chart -->
<canvas id="coinGrowthChart" height="550"></canvas>

<!-- Add a single toggle button below the chart -->
<!--<button id="toggleChartButton">Switch to Prediction</button>-->

<script>
    var chart; // Declare the chart variable
    var originalTokenomics; // Variable to store the original data
    var isDefaultView = true; // Flag to track the current view

    // Function to create the coin growth chart
    // Initialize the chart
    function createCoinGrowthChart(tokenomics) {
        var ctx = document.getElementById('coinGrowthChart').getContext('2d');

        // Get the parent div's dimensions
        var container = document.getElementById('graph-section');
        var containerWidth = container.offsetWidth;
        var containerHeight = container.offsetHeight;

        // Set canvas width and height based on the parent div's dimensions
        ctx.canvas.width = containerWidth;
        ctx.canvas.height = containerHeight;

        // Process the tokenomics data
        var dates = tokenomics.map(item => item.date);
        var cumulativeRewards = tokenomics.map(item => item.cumulative_reward);
        var unspentSubsidies = tokenomics.map(item => item.cumulative_subsidy - item.cumulative_subsidy_spent);
        var spentSubsidies = tokenomics.map(item => item.cumulative_subsidy_spent);
        var blockheight = tokenomics.map(item => item.first_block_height);

        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Block Reward',
                        data: cumulativeRewards,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: true,
                        stack: 'combined'
                    },
                    {
                        label: 'Spent Subsidy',
                        data: spentSubsidies,
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 2,
                        fill: true,
                        stack: 'combined'
                    },
                    {
                        label: 'Unspent Subsidy',
                        data: unspentSubsidies,
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2,
                        fill: true,
                        stack: 'combined'
                    },
                    {
                        label: 'Block Height',
                        data: blockheight,
                        backgroundColor: 'rgba(0, 0, 0, 0)', // Fully transparent
                        borderColor: 'rgba(0, 0, 0, 0)', // Fully transparent
                        borderWidth: 0, // No border
                        fill: false, // No fill
                        // other options
                    }
                ]
            },
            options: {
                plugins: {
                    legend: {
                        display: true,
                        onClick: null, // Disable click events on the legend
                        labels: {
                            filter: function (item, chart) {
                                // Filter out the dataset label from the legend
                                return item.text !== 'Block Height';
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        axis: 'x', // Ensure tooltips are shown for all datasets at the current x position
                        callbacks: {
                            label: function (context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                var value = context.parsed.y;
                                var units = ['SC', 'KS', 'MS', 'GS', 'TS'];
                                var unitIndex = 0;

                                while (value >= 1000 && unitIndex < units.length - 1) {
                                    value /= 1000;
                                    unitIndex++;
                                }

                                label += value.toFixed(2) + ' ' + units[unitIndex];
                                return label;
                            },
                            title: function (tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            beforeBody: function (tooltipItems) {
                                var index = tooltipItems[0].dataIndex;

                                var cumulativeReward = chart.data.datasets[0].data[index];
                                var unspentSubsidy = chart.data.datasets[1].data[index];
                                var spentSubsidy = chart.data.datasets[2].data[index];
                                var blockheight = chart.data.datasets[3].data[index];

                                var totalSubsidy = (unspentSubsidy || 0) + (spentSubsidy || 0);
                                var totalSupply = cumulativeReward + (spentSubsidy || 0);

                                var values = [totalSubsidy, totalSupply];
                                var units = ['SC', 'KS', 'MS', 'GS', 'TS'];

                                function formatValue(value) {
                                    var unitIndex = 0;
                                    while (value >= 1000 && unitIndex < units.length - 1) {
                                        value /= 1000;
                                        unitIndex++;
                                    }
                                    return value.toFixed(2) + ' ' + units[unitIndex];
                                }

                                return [
                                    'Total Supply: ' + formatValue(totalSupply),
                                    'Total Subsidy: ' + formatValue(totalSubsidy),
                                    //'Block height: ' + blockheight
                                ];
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 0 // Disable the dots
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'year',
                            displayFormats: {
                                'year': 'YYYY'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Year'
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        stacked: true,
                        title: {
                            display: true,
                            text: 'Number of Siacoins (in Billions)',
                        },
                        ticks: {
                            callback: function (value) {
                                return (value / 1e9).toFixed(0) + 'GS';
                            }
                        }
                    }
                }
            }
        });
    }


    // Function to update the chart with recent 3 months and 2 years projection
    function updateChartWithProjection(tokenomics) {
        // Calculate the last date in the data
        var lastDate = moment(tokenomics[tokenomics.length - 1].date);
        var threeMonthsAgo = moment(lastDate).subtract(6, 'months');

        // Filter the tokenomics data to include only the last 3 months
        var recentData = tokenomics.filter(item => moment(item.date).isAfter(threeMonthsAgo));

        // Extract relevant data for recent 3 months
        var dates = recentData.map(item => item.date);
        var cumulativeRewards = recentData.map(item => item.cumulative_reward);
        var unspentSubsidies = recentData.map(item => item.cumulative_subsidy - item.cumulative_subsidy_spent);
        var spentSubsidies = recentData.map(item => item.cumulative_subsidy_spent);

        // Calculate linear growth rates
        var rewardGrowthRate = (cumulativeRewards[cumulativeRewards.length - 1] - cumulativeRewards[0]) / (recentData.length - 1);
        var unspentSubsidyGrowthRate = (unspentSubsidies[unspentSubsidies.length - 1] - unspentSubsidies[0]) / (recentData.length - 1);
        var spentSubsidyGrowthRate = (spentSubsidies[spentSubsidies.length - 1] - spentSubsidies[0]) / (recentData.length - 1);

        // Add projection data
        for (var m = 1; m <= 60; m++) { // Add projection for the next 2 years (24 months)
            var nextDate = moment(lastDate).add(m, 'months').format('YYYY-MM-DD');
            dates.push(nextDate);
            cumulativeRewards.push(cumulativeRewards[cumulativeRewards.length - 1] + rewardGrowthRate);
            unspentSubsidies.push(unspentSubsidies[unspentSubsidies.length - 1] + unspentSubsidyGrowthRate);
            spentSubsidies.push(spentSubsidies[spentSubsidies.length - 1] + spentSubsidyGrowthRate);
        }

        // Update the chart data
        chart.data.labels = dates;
        chart.data.datasets[0].data = cumulativeRewards;
        chart.data.datasets[1].data = spentSubsidies;
        chart.data.datasets[2].data = unspentSubsidies;
        // chart.options.scales.y.beginAtZero = true; // Ensure the y-axis starts at zero
        chart.update();
    }


    // Function to revert the chart to its original state
    function revertChart(tokenomics) {
        // Process the tokenomics data
        var dates = tokenomics.map(item => item.date);
        var cumulativeRewards = tokenomics.map(item => item.cumulative_reward);
        var unspentSubsidies = tokenomics.map(item => item.cumulative_subsidy - item.cumulative_subsidy_spent);
        var spentSubsidies = tokenomics.map(item => item.cumulative_subsidy_spent);
        var blockheight = tokenomics.map(item => item.first_block_height);

        // Update the chart data
        chart.data.labels = dates;
        chart.data.datasets[0].data = cumulativeRewards;
        chart.data.datasets[1].data = spentSubsidies;
        chart.data.datasets[2].data = unspentSubsidies;
        // chart.options.scales.y.beginAtZero = true; // Ensure the y-axis starts at zero
        chart.update();
    }

    // Tokenomics data
    var tokenomics = <?php echo json_encode($data); ?>;
    originalTokenomics = JSON.parse(JSON.stringify(tokenomics)); // Store the original data
    createCoinGrowthChart(tokenomics);

    // Add event listener to the toggle button
    document.getElementById('toggleChartButton').addEventListener('click', function () {
        if (isDefaultView) {
            updateChartWithProjection(originalTokenomics);
            this.textContent = 'Switch to Past';
        } else {
            revertChart(originalTokenomics);
            this.textContent = 'Switch to Prediction';
        }
        isDefaultView = !isDefaultView;
    });
</script>
<body>
<section id="graph-section" class="bg-secondary p-3 rounded-3 mt-4">
        
            <section class="graph-container">

            </section></section>
</body>