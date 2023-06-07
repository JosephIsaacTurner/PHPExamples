<?php
    # Make sure you have the following script in your html file: 
    # <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>;
    function generateHistogram($data, $columnName) {
        static $count = 0;
        $count++;
        if ($count > 10) {
            $count = 0;
        }
        static $i = 77;
        $i--;
        if ($i == 0) {
            $i = 77;
        }
        // Extract the column values from the data array
        $columnValues = array_column($data, $columnName);

        // Count the occurrences of each unique value
        $counts = array_count_values($columnValues);

        // Prepare the labels and counts for the chart
        $labels = array_keys($counts);
        $countData = array_values($counts);
        // Sort the data and labels in descending order based on values
        array_multisort($countData, SORT_DESC, $labels);

        // Convert the sorted data and labels to JSON
        $sortedCountData = json_encode($countData);
        $sortedLabels = json_encode($labels);
        
        $totalBars = count($labels);
        $gradientColors = generateGradientColors($totalBars); // Generate gradient colors based on the number of bars

        $chart = '<div style="width: 80%; margin: 0 auto;">
                        <canvas id="histogram-chart' . $count . $i. '" style="width: 500px !important; height: 200px !important;"></canvas>
                    </div>
            <script>
                const ctx' . $count . $i .' = document.getElementById("histogram-chart' . $count .  $i.'").getContext("2d");
                new Chart(ctx' . $count . $i. ', {
                    type: "bar",
                    data: {
                        labels: ' . $sortedLabels . ',
                        datasets: [{
                            label: "Count by '. $columnName . '",
                            data: ' . $sortedCountData . ',
                            backgroundColor: ' . json_encode($gradientColors) . ',
                            borderColor: "rgba(75, 192, 192, 1)",
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                precision: 0
                            }
                        }
                    }
                });
            </script>';
        return $chart;
    }

    function generateGradientColors($totalBars) {
        #If you want, put in your own custom palette in place of this one;
        $palette = ["#00646680","#065a6080","#0b525b80","#14455280","#1b3a4b80","#212f4580","#27264080","#31224480","#3e1f4780","#4d194d80"];
        $colors = [];
        $paletteSize = count($palette);
        $step = 1 / ($totalBars + 1);

        for ($i = 0; $i < $totalBars; $i++) {
            $index = floor($i * $paletteSize / $totalBars) % $paletteSize;
            $colors[] = $palette[$index];
        }
        return $colors;
    }

    function generateMultiHistogram($data, $columnNames) {
        $charts = array();
        foreach($columnNames as $i=>$columnName){
            $charts[$i] = generateHistogram($data, $columnName);
        }
        $styleString = "<style>
        /* Style the tab */
        .tab {
        overflow: hidden;
        border: 1px solid #ccc;
        background-color: #f1f1f1;
        }    
        /* Style the buttons that are used to open the tab content */
        .tab button {
        width: auto !important;
        padding: 12px 10px !important;
        background-color: inherit;
        float: left;
        border: none !important;
        border-radius: 0 !important;
        outline: none;
        cursor: pointer;
        transition: 0.3s;
        }
        
        /* Change background color of buttons on hover */
        .tab button:hover {
        background-color: #ddd;
        }
        
        /* Create an active/current tablink class */
        .tab button.active {
        background-color: #ccc;
        }
        
        /* Style the tab content */
        .tabcontent {
        display: none;
        padding: 6px 12px;
        border: 1px solid #ccc;
        border-top: none;
        }
        </style>";
        
        // Create the HTML/JavaScript string for the histogram chart
        $chartHeader = '<div class="tab">';
        $chartContent = "";

        foreach($columnNames as $i=>$column){
            if ($i == 0){
                $active = "active";
                $display = 'style="display: block;"';
            }
            else{
                $active = "";
                $display = '';
            }
            $chartHeader .= "<button class='tablinks $active' onclick='openColumn(event, ". '"' . $column . '"' . ")'>$column</button>";
            $chartContent .= "<div id='$column' class='tabcontent' $display>
                                ". $charts[$i]. "
                            </div>";
        }
        $chartHeader .= '</div>';

        $chartString = "$styleString
                        <div class='chartParent' style='width: 80%; margin: 0 auto;'>
                        $chartHeader
                        $chartContent
                        </div>
                        ";
        $chartString .= '
        <script>
            function openColumn(evt, columnName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tabcontent");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tablinks");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(columnName).style.display = "block";
            evt.currentTarget.className += " active";
            }
        </script>
        ';
        return $chartString;
    }

    function getColumnNames($data) {
        if (empty($data)) {
            return array(); // Return an empty array if there are no rows in the data
        }
        $firstRow = reset($data); // Get the first row of the data
        $columnNames = array_keys($firstRow); // Extract the column names
        return $columnNames;
    }

    function queryToArray($connection, $query){
        // This function accepts a database connection and SQL query as parameters.
        // The query is executed, and the results are returned as an array of keyed arrays.

        $result = mysqli_query($connection, $query);

        $data = array(); // Initialize an empty array to store the data

        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row; // Append each row to the data array
        }
        return $data;
    }
    
?>
