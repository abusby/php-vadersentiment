<?php
    error_reporting(E_ALL);
    require_once "vadersentiment.php";
    
    $textToTest = "VADER is smart, handsome, and funny.";
    $textToTest2 = "I love capstone design.";
    $textToTest3 = "I am depressed.";
    
    $sentimenter = new SentimentIntensityAnalyzer();
    $result = $sentimenter->getSentiment($textToTest3);
    print_r($result);
    echo("Hello");
?>