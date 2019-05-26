<?php

require_once "sentitext.php";

//Constants

// (empirically derived mean sentiment intensity rating increase for booster words)
define("B_INCR",0.293);
define("B_DECR",-0.293);

// (empirically derived mean sentiment intensity rating increase for using
// ALLCAPs to emphasize a word)
define("C_INCR", 0.733);

define("N_SCALAR", -0.74);

// for removing punctuation
//REGEX_REMOVE_PUNCTUATION = re.compile('[%s]' % re.escape(string.punctuation))



			 
			 
$NEGATE = array("aint", "arent", "cannot", "cant", "couldnt", "darent", "didnt", "doesnt",
	"ain't", "aren't", "can't", "couldn't", "daren't", "didn't", "doesn't",
	"dont", "hadnt", "hasnt", "havent", "isnt", "mightnt", "mustnt", "neither",
	"don't", "hadn't", "hasn't", "haven't", "isn't", "mightn't", "mustn't",
	"neednt", "needn't", "never", "none", "nope", "nor", "not", "nothing", "nowhere",
	"oughtnt", "shant", "shouldnt", "uhuh", "wasnt", "werent",
	"oughtn't", "shan't", "shouldn't", "uh-uh", "wasn't", "weren't",
	"without", "wont", "wouldnt", "won't", "wouldn't", "rarely", "seldom", "despite");

//booster/dampener 'intensifiers' or 'degree adverbs'
//http://en.wiktionary.org/wiki/Category:English_degree_adverbs

$BOOSTER_DICT = array("absolutely"=> B_INCR, "amazingly"=> B_INCR, "awfully"=> B_INCR, "completely"=> B_INCR, "considerably"=> B_INCR,
 "decidedly"=> B_INCR, "deeply"=> B_INCR, "effing"=> B_INCR, "enormously"=> B_INCR,
 "entirely"=> B_INCR, "especially"=> B_INCR, "exceptionally"=> B_INCR, "extremely"=> B_INCR,
 "fabulously"=> B_INCR, "flipping"=> B_INCR, "flippin"=> B_INCR,
 "fricking"=> B_INCR, "frickin"=> B_INCR, "frigging"=> B_INCR, "friggin"=> B_INCR, "fully"=> B_INCR, "fucking"=> B_INCR,
 "greatly"=> B_INCR, "hella"=> B_INCR, "highly"=> B_INCR, "hugely"=> B_INCR, "incredibly"=> B_INCR,
 "intensely"=> B_INCR, "majorly"=> B_INCR, "more"=> B_INCR, "most"=> B_INCR, "particularly"=> B_INCR,
 "purely"=> B_INCR, "quite"=> B_INCR, "really"=> B_INCR, "remarkably"=> B_INCR,
 "so"=> B_INCR, "substantially"=> B_INCR,
 "thoroughly"=> B_INCR, "totally"=> B_INCR, "tremendously"=> B_INCR,
 "uber"=> B_INCR, "unbelievably"=> B_INCR, "unusually"=> B_INCR, "utterly"=> B_INCR,
 "very"=> B_INCR,
 "almost"=> B_DECR, "barely"=> B_DECR, "hardly"=> B_DECR, "just enough"=> B_DECR,
 "kind of"=> B_DECR, "kinda"=> B_DECR, "kindof"=> B_DECR, "kind-of"=> B_DECR,
 "less"=> B_DECR, "little"=> B_DECR, "marginally"=> B_DECR, "occasionally"=> B_DECR, "partly"=> B_DECR,
 "scarcely"=> B_DECR, "slightly"=> B_DECR, "somewhat"=> B_DECR,
 "sort of"=> B_DECR, "sorta"=> B_DECR, "sortof"=> B_DECR, "sort-of"=> B_DECR);

// check for special case idioms using a sentiment-laden keyword known to SAGE
$SPECIAL_CASE_IDIOMS = array("the shit"=> 3, "the bomb"=> 3, "bad ass"=> 1.5, "yeah right"=> -2,
                       "cut the mustard"=> 2, "kiss of death"=> -1.5, "hand to mouth"=> -2);
##Static methods##

/*
    Normalize the score to be between -1 and 1 using an alpha that
    approximates the max expected value
*/
function normalize($score, $alpha=15){
	$norm_score = $score/sqrt(($score*$score) + $alpha);
    return $norm_score;
}







    

/*
	Give a sentiment intensity score to sentences.
*/

class SentimentIntensityAnalyzer{

	private $lexicon_file = "";
	private $lexicon = "";
	
	private $current_sentitext = null;
	
    function __construct($lexicon_file="vader_sentiment_lexicon.txt"){
		//Not sure about this as it forces lexicon file to be in the same directory as executing script
        $this->lexicon_file = realpath(dirname(__FILE__)) . "/" . $lexicon_file;
        $this->lexicon = $this->make_lex_dict();
	}
	
	
	/*
		Determine if input contains negation words
	*/
	function IsNegated($wordToTest, $include_nt=true){
		global $NEGATE;
		if(in_array($wordToTest,$NEGATE)){
			return true;
		}

		if ($include_nt) {
			if (strpos($wordToTest,"n't")){
				return true;
			}
		}

		return false;
	}
	

	/*
		Convert lexicon file to a dictionary
	*/
    function make_lex_dict(){
        $lex_dict = array();
        $fp = fopen($this->lexicon_file,"r");
		if(!$fp){
			die("Cannot load lexicon file");
		}
		
		while (($line = fgets($fp, 4096)) !== false) {
           
            list($word, $measure) = explode("\t",trim($line));
			//.strip().split('\t')[0:2]
			$lex_dict[$word] = $measure;
			//lex_dict[word] = float(measure)
		}
        return $lex_dict;
	}
	
	
	private function IsKindOf($firstWord,$secondWord){
		return "kind" === strtolower($firstWord) && "of" === strtolower($secondWord);
	}
	
	private function IsBoosterWord($word){
		global $BOOSTER_DICT;
		return array_key_exists(strtolower($word),$BOOSTER_DICT);
	}
	
	private function getBoosterScaler($word){
		return $BOOSTER_DICT[strtolower($word)];
	}
	
	private function IsInLexicon($word){
		$lowercase = strtolower($word);
		return array_key_exists($lowercase,$this->lexicon);
	}
	private function IsUpperCaseWord($word){
		return ctype_upper($word);
	}
	
	private function getValenceFromLexicon($word){
		return $this->lexicon[strtolower($word)];
	}
	
	private function getTargetWordFromContext($wordInContext){
		return $wordInContext[count($wordInContext)-1];
	}

	/*
		Gets the precedding two words to check for emphasis
	*/
	private function getWordInContext($wordList,$currentWordPosition){
		$precedingWordList = array();
		
		//push the actual word on to the context list
		array_unshift($precedingWordList,$wordList[$currentWordPosition]);
		//If the word position is greater than 2 then we know we are not going to overflow
		if(($currentWordPosition-1)>=0){
			array_unshift($precedingWordList,$wordList[$currentWordPosition-1]);
		}else{
			array_unshift($precedingWordList,"");
		}
		if(($currentWordPosition-2)>=0){
			array_unshift($precedingWordList,$wordList[$currentWordPosition-2]);
		}else{
			array_unshift($precedingWordList,"");
		}
		if(($currentWordPosition-3)>=0){
			array_unshift($precedingWordList,$wordList[$currentWordPosition-3]);
		}else{
			array_unshift($precedingWordList,"");
		}
		return $precedingWordList;
	}
	
	
	/*
		Return a float for sentiment strength based on the input text.
        Positive values are positive valence, negative value are negative
        valence.
	*/	
    function getSentiment($text){
        $this->current_sentitext = new SentiText($text);
  
        $sentiments = array();
        $words_and_emoticons = $this->current_sentitext->words_and_emoticons;

		for($i=0;$i<count($words_and_emoticons)-1;$i++){
			
            $valence = 0.0;
            $wordBeingTested = $words_and_emoticons[$i];
			
			//If this is a booster word add a 0 valances then go to next word as it does not express sentiment directly
           /* if ($this->IsBoosterWord($wordBeingTested)){
				echo "\t\tThe word is a booster word: setting sentiment to 0.0\n";
			}*/
			
			//If the word is not in the Lexicon then it does not express sentiment. So just ignore it.
			if($this->IsInLexicon($wordBeingTested)){
				//Special case because kind is in the lexicon so the modifier kind of needs to be skipped
				if("kind" !=$words_and_emoticons[$i] && "of" != $words_and_emoticons[$i+1]){
					$valence = $this->getValenceFromLexicon($wordBeingTested);

					$wordInContext = $this->getWordInContext($words_and_emoticons,$i);
					//If we are here then we have a word that enhance booster words
					$valence = $this->adjustBoosterSentiment($wordInContext,$valence);
				}

				
			}
			array_push($sentiments,$valence);
		}
		//Once we have a sentiment for each word adjust the sentimest if but is present
        $sentiments = $this->_but_check($words_and_emoticons, $sentiments);

        return $this->score_valence($sentiments, $text);
	}
	
	
	
	
	private function applyValenceCapsBoost($targetWord,$valence){
		if($this->IsUpperCaseWord($targetWord) && $this->current_sentitext->is_cap_diff){
			if($valence > 0){
				$valence += C_INCR;
			}
			else{
				$valence -= C_INCR;
			}
		}
		return $valence;
	}
	
	/*
		Check if the preceding words increase, decrease, or negate/nullify the
		valence
	 */
	private function boosterScaleAdjustment($word, $valence){
		$scalar = 0.0;
		if(!$this->IsBoosterWord($word)){
			return $scalar;
		}
		
		$scalar = $this->getBoosterScaler($word);
		
		if ($valence < 0){
			$scalar *= -1;
		}
	   //check if booster/dampener word is in ALLCAPS (while others aren't)
		$scalar = $this->applyValenceCapsBoost($word,$scalar);
		
		return $scalar;
	}
	
	// dampen the scalar modifier of preceding words and emoticons
	// (excluding the ones that immediately preceed the item) based
	// on their distance from the current item.
	private function dampendBoosterScalerByPosition($booster,$position){
		if(0===$booster){
			return $booster;
		}
		if(1==$position){
			return $booster*0.95;
		}
		if(2==$position){
			return $booster*0.9;
		}
		return $booster;
	}
    
	
	private function adjustBoosterSentiment($wordInContext,$valence){
        //The target word is always the last word
		$targetWord = $this->getTargetWordFromContext($wordInContext);

		//check if sentiment laden word is in ALL CAPS (while others aren't) and apply booster
		$valence = $this->applyValenceCapsBoost($targetWord,$valence);
		
		$valence = $this->modifyValenceBasedOnContext($wordInContext,$valence);
		return $valence;
	}
		
	private function modifyValenceBasedOnContext($wordInContext,$valence){

			$wordToTest = $this->getTargetWordFromContext($wordInContext);
			//if($this->IsInLexicon($wordToTest)){
			//	continue;
			//}
			for($i=0;$i<count($wordInContext)-1;$i++){
				$scalarValue = $this->boosterScaleAdjustment($wordInContext[$i], $valence);
				$scalarValue = $this->dampendBoosterScalerByPosition($scalarValue,$i);
				$valence = $valence+$scalarValue;
			}

			
			$valence = $this->_never_check($wordInContext, $valence);

			$valence = $this->_idioms_check($wordInContext, $valence);

				# future work: consider other sentiment-laden idioms
				# other_idioms =
				# {"back handed": -2, "blow smoke": -2, "blowing smoke": -2,
				#  "upper hand": 1, "break a leg": 2,
				#  "cooking with gas": 2, "in the black": 2, "in the red": -2,
				#  "on the ball": 2,"under the weather": -2}

			$valence = $this->_least_check($wordInContext, $valence);
			
		
        return $valence;
	}
	
    function _least_check($wordInContext, $valence){
        # check for negation case using "least"
		//if the previous word is least"
        if(strtolower($wordInContext[2]) == "least"){
			//but not "at least {word}" "very least {word}"
            if (strtolower($wordInContext[1]) != "at" && strtolower($wordInContext[1]) != "very"){
                $valence = $valence*N_SCALAR;
			}
		}
        return $valence;
	}

	
    function _but_check($words_and_emoticons, $sentiments){
        # check for modification in sentiment due to contrastive conjunction 'but'
		$bi = array_search("but",$words_and_emoticons);
		if(!$bi){
			$bi = array_search("BUT",$words_and_emoticons);
		}
        if($bi){
			for($si=0;$si<count($sentiments);$si++){
				if($si<$bi){
					$sentiments[$si] = $sentiments[$si]*0.5;
				}elseif($si> $bi){
					$sentiments[$si] = $sentiments[$si]*1.5;
				}
			}
		}
        return $sentiments;
	}

    function _idioms_check($wordInContext, $valence){
        $onezero = sprintf("%s %s",$wordInContext[2], $wordInContext[3]);

        $twoonezero = sprintf("%s %s %s",$wordInContext[1],
                                       $wordInContext[2], $wordInContext[3]);

        $twoone = sprintf("%s %s",$wordInContext[1], $wordInContext[2]);

        $threetwoone = sprintf("%s %s %s",$wordInContext[0],
                                        $wordInContext[1], $wordInContext[2]);

        $threetwo = sprintf("%s %s",$wordInContext[0], $wordInContext[1]);

		$zeroone = sprintf("%s %s",$wordInContext[3], $wordInContext[2]);
		
		$zeroonetwo = sprintf("%s %s %s",$wordInContext[3], $wordInContext[2], $wordInContext[1]);
		
        $sequences = array($onezero, $twoonezero, $twoone, $threetwoone, $threetwo);

        foreach($sequences as $seq){
			global $SPECIAL_CASE_IDIOMS;
            if (array_key_exists(strtolower($seq), $SPECIAL_CASE_IDIOMS)){
                $valence = $SPECIAL_CASE_IDIOMS[$seq];
                break;
			}
			
			
/*
			Positive idioms check.  Not implementing it yet
			if(count($words_and_emoticons)-1 > $i){
				$zeroone = sprintf("%s %s",$words_and_emoticons[$i], $words_and_emoticons[$i+1]);
			   if (in_array($zeroone, SPECIAL_CASE_IDIOMS)){
					$valence = SPECIAL_CASE_IDIOMS[$zeroone];
				}
			}
			if(count($words_and_emoticons)-1 > $i+1){
				$zeroonetwo = sprintf("%s %s %s",$words_and_emoticons[$i], $words_and_emoticons[$i+1], $words_and_emoticons[$i+2]);
				if (in_array($zeroonetwo, SPECIAL_CASE_IDIOMS)){
					$valence = SPECIAL_CASE_IDIOMS[$zeroonetwo];
				}
			}
*/

			// check for booster/dampener bi-grams such as 'sort of' or 'kind of'
			if($this->IsBoosterWord($threetwo) || $this->IsBoosterWord($twoone)){
				$valence = $valence+B_DECR;
			}
		}
        return $valence;
	}

    function _never_check($wordInContext,$valance){
		//If the sentiment word is preceded by never so/this we apply a modifier
		$neverModifier = 0;
		if("never" == $wordInContext[0]){
			$neverModifier = 1.25;
		}else if("never" == $wordInContext[1]){
			$neverModifier = 1.5;
		}
		if("so" == $wordInContext[1] || "so"== $wordInContext[2] || "this" == $wordInContext[1] || "this" == $wordInContext[2]){
			$valance *= $neverModifier;
		}
		
		//if any of the words in context are negated words apply negative scaler
		foreach($wordInContext as $wordToCheck){
			if($this->IsNegated($wordToCheck)){
				$valance *= B_DECR;
			}
		}
		

        return $valance;
	}
	
    function _punctuation_emphasis($sum_s, $text){
        # add emphasis from exclamation points and question marks
        $ep_amplifier = $this->_amplify_ep($text);
        $qm_amplifier = $this->_amplify_qm($text);
        $punct_emph_amplifier = $ep_amplifier+$qm_amplifier;
        return $punct_emph_amplifier;
	}
    
	function _amplify_ep($text){
        # check for added emphasis resulting from exclamation points (up to 4 of them)
        $ep_count = substr_count($text,"!");
        if ($ep_count > 4){
            $ep_count = 4;
		}
        # (empirically derived mean sentiment intensity rating increase for
        # exclamation points)
        $ep_amplifier = $ep_count*0.292;
        return $ep_amplifier;
	}

    function _amplify_qm($text){
        # check for added emphasis resulting from question marks (2 or 3+)
        $qm_count = substr_count ($text,"?");
        $qm_amplifier = 0;
        if ($qm_count > 1){
            if ($qm_count <= 3){
                # (empirically derived mean sentiment intensity rating increase for
                # question marks)
                $qm_amplifier = $qm_count*0.18;
            }else{
                $qm_amplifier = 0.96;
			}
		}
        return $qm_amplifier;
	}

    function _sift_sentiment_scores($sentiments){
        # want separate positive versus negative sentiment scores
        $pos_sum = 0.0;
        $neg_sum = 0.0;
        $neu_count = 0;
        foreach($sentiments as $sentiment_score){
            if($sentiment_score > 0){
                $pos_sum += $sentiment_score +1; # compensates for neutral words that are counted as 1
			}
            if ($sentiment_score < 0){
                $neg_sum += $sentiment_score -1; # when used with math.fabs(), compensates for neutrals
			}
            if ($sentiment_score == 0){
                $neu_count += 1;
			}
		}
        return array($pos_sum, $neg_sum, $neu_count);
	}
    
	function score_valence($sentiments, $text){
        if ($sentiments){
            $sum_s = array_sum($sentiments);
            # compute and add emphasis from punctuation in text
            $punct_emph_amplifier = $this->_punctuation_emphasis($sum_s, $text);
            if ($sum_s > 0){
                $sum_s += $punct_emph_amplifier;
			}
            elseif  ($sum_s < 0){
                $sum_s -= $punct_emph_amplifier;
			}

            $compound = normalize($sum_s);
            # discriminate between positive, negative and neutral sentiment scores
            list($pos_sum, $neg_sum, $neu_count) = $this->_sift_sentiment_scores($sentiments);

            if ($pos_sum > abs($neg_sum)){
                $pos_sum += $punct_emph_amplifier;
			}
            elseif ($pos_sum < abs($neg_sum)){
                $neg_sum -= $punct_emph_amplifier;
			}

            $total = $pos_sum + abs($neg_sum) + $neu_count;
            $pos =abs($pos_sum / $total);
            $neg = abs($neg_sum / $total);
            $neu = abs($neu_count / $total);

        }else{
            $compound = 0.0;
			$pos = 0.0;
            $neg = 0.0;
            $neu = 0.0;
		}

        $sentiment_dict = 
            array("neg" => round($neg, 3),
             "neu" => round($neu, 3),
             "pos" => round($pos, 3),
             "compound" => round($compound, 4));

        return $sentiment_dict;
	}
}
	
?>