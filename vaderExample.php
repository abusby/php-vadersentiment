<?php
    error_reporting(E_ALL);
    require_once "vadersentiment.php";
    
    $textToTest = "VADER is smart, handsome, and funny.";
    $textToTest2 = "I love capstone design.";
    $textToTest3 = "I am depressed. Hello.";
	$happy = "I am happy.";
    
    $sentimenter = new SentimentIntensityAnalyzer();
    $sentimenter2 = new SentimentIntensityAnalyzer();
//    $result = $sentimenter->getSentiment($textToTest . $textToTest2 . $textToTest3);
    $result2 = $sentimenter2->getSentiment($happy);
    print_r($result);
    print_r($result2);
    echo("Hello");
?>
