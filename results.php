<?php
    include( "shared.php" );
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title>NGS graph generator</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>
    <div class="headerfooter">
        <div class="header">NGS graph generator</div>
        <a href=".">Scheduler</a>
        <a href="results.php">Results</a>
    </div>
<?php
    function compareFilenames( $a, $b )
    {
        $fileA = $a[ 'filename' ];
        $fileB = $b[ 'filename' ];
        $extA = pathinfo( $fileA, PATHINFO_EXTENSION );
        $extB = pathinfo( $fileB, PATHINFO_EXTENSION );

        if( $extA == $extB )
            return $fileA > $fileB;
        else
            return $extA > $extB;
    }

    if( $db = openDatabase( ) )
    {
?>
    <form name="emailFilter" method="get" action="results.php">
        <div id="filter">
            <p>
                <fieldset class="outer" id="filter">
                    <legend>Filter</legend>
                    <p>
                        <a class="button" href="results.php">All results</a>
                        <a class="button" href="results.php?inprogress=1">In progress</a>
                        <select name="email" onchange="emailFilter.submit( )">
                            <option value="">No filter</option>
<?php
        $query = $db->prepare( "SELECT DISTINCT email FROM jobs ORDER BY email" );
        $query->execute( )
            or die( "Query failed: " . $db->error );
        $result = $query->get_result( );

        while( $row = $result->fetch_assoc( ) )
        {
            $email = $row[ 'email' ];

            if( $_GET[ 'email' ] == $email )
                echo "            <option value=\"$email\" selected>$email</option>\n";
            else
                echo "            <option value=\"$email\">$email</option>\n";
        }

        $query->close( );
?>
                        </select>
                    </p>
                    <noscript>
                        <input type="submit" value="Filter by owner" />
                    </noscript>
                </fieldset>
            </p>
        </div>
        <div id="results">
            <p>
                <fieldset class="results">
                    <legend>Results</legend>
<?php
        $job        = isset($_GET[ 'job' ]) ? $_GET[ 'job' ] : NULL;
        $inprogress = isset($_GET[ 'inprogress' ]) ? $_GET[ 'inprogress' ] : NULL;
        $email      = isset($_GET[ 'email' ]) ? $_GET[ 'email' ] : NULL;

        $queryText = "SELECT " .
                        "jobs.id, " .
                        "jobs.arguments, " .
                        "jobs.timequeued, " .
                        "jobs.timestarted, " .
                        "jobs.timefinished, " .
                        "jobs.exitcode, " .
                        "jobs.email, " .
                        "jobs.abort " .
                        "FROM jobs";

        if( $job != NULL )
        {
            $title = "Job $job";
            $queryText = $queryText . " WHERE id = ?";
            $param = $job;

            $abortQuery = $db->prepare( "SELECT id FROM jobs WHERE timefinished = '0'" );

            $abortQuery->execute( )
                or die( "Query failed: " . $db->error );
            $result = $abortQuery->get_result( );

            // Abort
            if( $result->num_rows > 0 )
            {
                $links = $links . "<a class=\"button\"" .
                    " href=\"abort.php?job=$job\">Abort</a>\n";
            }

            $abortQuery->close( );
        }
        else if( $inprogress != NULL )
        {
            $title = "In progress";
            $queryText = $queryText . " WHERE timefinished = '0'";
        }
        else if( $email != NULL )
        {
            $title = "Owned by $email";
            $queryText = $queryText . " WHERE email = ?";
            $param = $email;

            $jobQuery = $db->prepare( "SELECT id FROM jobs " .
                "WHERE email = ? ORDER BY id" );
            $jobQuery->bind_param( "s", $email );

            $jobQuery->execute( )
                or die( "Query failed: " . $db->error );
            $result = $jobQuery->get_result( );

            $links = "Jobs: ";
            while( $row = $result->fetch_assoc( ) )
            {
                $links = $links . "<a class=\"button\"" .
                    " href=\"results.php?job=" . $row[ 'id' ] . "\">" .
                    $row[ 'id' ] . "</a> ";
            }

            $jobQuery->close( );
        }
        else
            $title = "All ";

        $queryText = $queryText . " ORDER BY timequeued DESC, resultsdir ASC ";

        $query = $db->prepare( $queryText );

        if( $param )
            $query->bind_param( "s", $param );

        // Run query to get the total number of rows
        $query->execute( )
            or die( "Query failed: " . $db->error );
        $result = $query->get_result( );
        $totalRows = $result->num_rows;

        $url = "results.php?";
        if( $job != NULL )
            $url = $url . "job=$job&";
        if( $email != NULL )
            $url = $url . "email=" . urlencode( $email ) . "&";
        if( $inprogress != NULL )
            $url = $url . "inprogress=$inprogress&";

        if( $result && $totalRows > 0 )
        {
            echo "<table id=\"results_table\">\n";
            echo "<thead>\n";
            echo "<tr>\n";
            echo "<th>ID</th>";
            echo "<th>Owner</th>";
            echo "<th>Arguments</th><th>Time queued</th>" .
                 "<th>Processing time</th><th>Result</th>\n";
            echo "</tr>\n";
            echo "</thead>\n";
            echo "<tbody>\n";
            while( ( $row = $result->fetch_assoc( ) ) )
            {
                $jobId              = $row[ 'id' ];
                $arguments          = $row[ 'arguments' ];
                $queued             = $row[ 'timequeued' ];
                $started            = $row[ 'timestarted' ];
                $finished           = $row[ 'timefinished' ];
                $exitcode           = $row[ 'exitcode' ];
                $email              = $row[ 'email' ];
                $abort              = $row[ 'abort' ];

                echo "<tr>\n";
                echo "<td>$jobId</td>\n";
                echo "<td>$email</td>\n";

                if( $arguments != "" )
                    echo "<td>$arguments</td>\n";
                else
                    echo "<td>None</td>\n";

                // Date queued
                if( $queued > 0 )
                    echo "<td>" . date( "G:i d/m/Y", $queued ) . "</td>\n";
                else
                    echo "<td></td>\n";

                if( $started > 0 )
                {
                    if( $finished > 0 )
                        $seconds = ( $finished - $started );
                    else
                        $seconds = ( time( ) - $started );

                    // Believe it or not, this is an integer divide
                    $hours      = ( $seconds - ( $seconds % 3600 ) ) / 3600;
                    $seconds    = $seconds % 3600;
                    $minutes    = ( $seconds - ( $seconds % 60 ) ) / 60;
                    $seconds    = $seconds % 60;
                    $seconds    = str_pad( $seconds, 2, "0", STR_PAD_LEFT );

                    if( $hours > 0 )
                    {
                        $minutes = str_pad( $minutes, 2, "0", STR_PAD_LEFT );
                        $duration = "$hours:$minutes:$seconds";
                    }
                    else
                    {
                        $duration = "$minutes:$seconds";
                    }

                    if( $exitcode == 0 )
                        echo "<td class=\"success\">";
                    else
                        echo "<td class=\"failure\">";

                    echo "<a href=\"output.php?id=$jobId\">$duration</a></td>\n";

                    if( $abort )
                        echo "<td>Aborted</td>\n";
                    else if( $finished > 0 )
                    {
                        if( $exitcode == 0 )
                        {
                            $fileLinks = "";

                            $resultsQuery = $db->prepare( "SELECT id, filename FROM results " .
                                "WHERE jobid = ? ORDER BY filename" );
                            $resultsQuery->bind_param( "s", $jobId );

                            $resultsQuery->execute( )
                                or die( "Query failed: " . $db->error );
                            $resultsResult = $resultsQuery->get_result( );

                            if( $resultsResult->num_rows > 0 )
                            {
                                $files = array();
                                while( $row = $resultsResult->fetch_assoc( ) )
                                    $files[] = array( 'id' => $row[ 'id' ], 'filename' => $row[ 'filename' ] );

                                usort( $files, 'compareFilenames' );

                                foreach( $files as $file )
                                {
                                    $id = $file[ 'id' ];
                                    $filename = $file[ 'filename' ];
                                    $ext = pathinfo( $filename, PATHINFO_EXTENSION );

                                    if( $ext == "zip" )
                                    {
                                        $linkClass = "zip";
                                    }
                                    else
                                    {
                                        $linkClass = "";
                                    }

                                    $fileLinks = $fileLinks .
                                        "<a class=\"$linkClass\" href=\"file.php?fileId=$id&jobId=$jobId\">$filename</a> ";
                                }

                                echo "<td>$fileLinks</td>\n";
                            }
                            else
                            {
                                echo "<td>No results</td>\n";
                            }

                            $resultsQuery->close( );
                        }
                        else
                        {
                            echo "<td>FAILED (exit code $exitcode)</td>\n";
                        }
                    }
                    else
                        echo "<td>" .
                                "<a class=\"button\" href=\"abort.php?job=$jobId\">Abort</a> " .
                                "<a class=\"button\" href=\"abort.php?job=$jobId&delete=1\">Delete</a>" .
                            "</td>\n";
                }
                else
                {
                    echo "<td>Not started</td>\n";
                }

                echo "</tr>\n";
            }
            echo "</tbody>\n";
            echo "</table>\n";

        }
        else
        {
            echo "<p>No results</p>\n";
        }

        $query->close( );
?>
                </fieldset>
            </p>
        </div>
    </form>
<?php
        closeDatabase( $db );
    }
?>
    <div class="headerfooter footer"></div>
</body>
</html>
