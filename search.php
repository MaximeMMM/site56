<?php

// Connect to the database
$db = new mysqli('hostname', 'username', 'password', 'database');

// Check for errors
if ($db->connect_error) {
  die("Connection failed: " . $db->connect_error);
}

// Extract the search query from the form data
$query = $db->real_escape_string($_GET['query']);

// Perform the search
$result = $db->query("SELECT * FROM table WHERE column LIKE '%$query%'");

// Check for errors
if (!$result) {
  die("Error: " . $db->error);
}

// Return the search results as an HTML table
echo '<table>';
while ($row = $result->fetch_assoc()) {
  echo '<tr>';
  echo '<td>' . $row['column1'] . '</td>';
  echo '<td>' . $row['column2'] . '</td>';
  echo '</tr>';
}
echo '</table>';

// Close the database connection
$db->close();

