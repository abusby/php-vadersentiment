#VADER-Sentiment-Analysis

VADER (Valence Aware Dictionary and sEntiment Reasoner) is a lexicon and rule-based sentiment analysis tool that is specifically attuned to sentiments expressed in social media. It is fully open-sourced under the MIT License (we sincerely appreciate all attributions and readily accept most contributions, but please don't hold us liable).

##Introduction

This is a php port of the vader sentiment analysis tool orginally written in python and found https://github.com/cjhutto/vaderSentiment


##Example code

```php
require_once "vadersentiment.php";

$textToTest = "VADER is smart, handsome, and funny.";

$sentimenter = new new SentimentIntensityAnalyzer();
$result = $sentimenter->getSentiment($$textToTest);

print_r($result);
```

##This README file describes the dataset of the paper:

VADER: A Parsimonious Rule-based Model for Sentiment Analysis of Social Media Text 
(by C.J. Hutto and Eric Gilbert) 
Eighth International Conference on Weblogs and Social Media (ICWSM-14). Ann Arbor, MI, June 2014. 

##Citation Information

If you use either the dataset or any of the VADER sentiment analysis tools (VADER sentiment lexicon or Python code for rule-based sentiment analysis engine) in your research, please cite the above paper. For example: 

Hutto, C.J. & Gilbert, E.E. (2014). VADER: A Parsimonious Rule-based Model for Sentiment Analysis of Social Media Text. Eighth International Conference on Weblogs and Social Media (ICWSM-14). Ann Arbor, MI, June 2014. 
