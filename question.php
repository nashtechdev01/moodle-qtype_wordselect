<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * wordselect question definition class.
 *
 * @package    qtype_wordselect
 * @copyright  Marcus Green 2018)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Represents a wordselect question.
 *
 * @copyright  2016 Marcus Green

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_wordselect_question extends question_graded_automatically_with_countback {

    /**
     *
     * @var number how many items clicked on are not correct answers
     */
    public $wrongresponsecount;
    /**
     *
     * @var number how many items clicked on are  correct answers
     */
    public $rightresponsecount;

    /**
     * the characters indicating a field to fill i.e. [cat] creates
     * field where the correct answer is cat
     * @var string
     */
    public $delimitchars = "[]";

    /**
     * the place number with p appended, i.e. p0 p1 etc
     * @param number $place
     * @return string the question-type variable name
     */
    public function field($place) {
        return 'p' . $place;
    }

    /**
     * The text with delimiters removed so the user cannot see
     * which words are the ones that should be selected. So The cow [jumped]
     * becomes The cow jumped
     */
    public function get_words() {
        $questiontextnodelim = $this->questiontext;
        $l = substr($this->delimitchars, 0, 1);
        $r = substr($this->delimitchars, 1, 1);
        $text = $this->get_questiontext_exploded($this->questiontext);
        $questiontextnodelim = preg_replace('/\\' . $l . '/', '', $text);
        $questiontextnodelim = preg_replace('/\\' . $r . '/', '', $questiontextnodelim);
         /* remove any hyperlinks from candidates for selection, this means that
         * things like audio files will be rendered for the multimedia filter
         */
        $this->selectable = preg_replace('/<a.*?<\\/a>/', '', $questiontextnodelim);
        $this->selectable = strip_tags($this->selectable);

        $allwords = preg_split('@[\s+]@u', $questiontextnodelim);
        return $allwords;
    }

    /**
     * Part of an experiment, can probably be deleted
     *
     * @param string $questiontext
     * @return string
     */
    public function get_unselectable_words($questiontext) {
        $questiontext = $this->get_questiontext_exploded($questiontext);
        $allwords = preg_split('/[\s\n]/', $questiontext);
        $unselectable = array();
        $started = false;
        foreach ($allwords as $key => $word) {
            $start = substr($word, 0, 1);
            $len = strlen($word);
            $end = substr($word, $len - 1, $len);
            if ($start == "*") {
                $started = true;
            }
            if ($end == "*") {
                $started = false;
                $unselectable[$key] = $word;
            }
            if ($started == true) {
                $unselectable[$key] = $word;
            }
        }
        return $unselectable;
    }
    /**
     * Put a space before and after tags so they get split as words
     * This allows the use of tables amongst other html things
     *
     * @param string $questiontext
     * @return string
     */
    public static function get_questiontext_exploded($questiontext) {
        $text = str_replace('>', '> ', $questiontext);
        $text = str_replace('<', ' <', $text);
        return $text;
    }

    /**
     * Split the question text into words delimited by spaces
     * then return an array of all the words that are correct
     * i.e. surrounded by the delimit chars. Note that
     * word in this context means any string that can be separated
     * by a space marker so that will include html etc
     *
     * @param string $questiontext
     * @param string $delimitchars
     * @return array
     */
    public static function get_correct_places($questiontext, $delimitchars) {
        $correctplaces = array();
        $text = self::get_questiontext_exploded($questiontext);
        $allwords = preg_split('/[\s\n]/', $text);
        $l = substr($delimitchars, 0, 1);
        $r = substr($delimitchars, 1, 1);
        foreach ($allwords as $key => $word) {
            $regex = '/\\' . $l . '.*\\' . $r . '/';
            if (preg_match($regex, $word)) {
                $correctplaces[] = $key;
            }
        }
        return $correctplaces;
    }

    /**
     * Return an array of the question type variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        $wordcount = count($this->get_words());
        for ($key = 0; $key < $wordcount; $key++) {
            $data['p' . $key] = PARAM_RAW_TRIMMED;
        }
        return $data;
    }

    /**
     * summary of response shown in the responses report
     * @param array $response
     * @return string
     */
    public function summarise_response(array $response) {
        $summary = '';
        $allwords = $this->get_words();
        foreach ($response as $index => $value) {
            $summary .= " " . $allwords[substr($index, 1)] . " ";
        }
        return $summary;
    }

    /**
     * At runtime, decide if a word has been clicked on to select
     *
     * @param number $place
     * @param array $response
     * @return boolean
     */
    public function is_word_selected($place, $response) {
        $responseplace = 'p' . $place;
        if (isset($response[$responseplace]) && (($response[$responseplace] == "on" ) || ($response[$responseplace] == "true" ) )) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * Have any words been selected?
     *
     * @param array $response
     * @return boolean
     */
    public function is_complete_response(array $response) {
        foreach ($response as $item) {
            if ($item == "on") {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * Get string validation to display for user.
     *
     * @param array $response
     * @return string
     */
    public function get_validation_error(array $response) {
        if (! $this->is_complete_response($response)) {
            return get_string('pleaseselectananswer', 'qtype_wordselect');
        }

        return '';
    }

    /**
     * if you are moving from viewing one question to another this will
     * discard the processing if the answer has not changed. If you don't
     * use this method it will constantantly generate new question steps and
     * the question will be repeatedly set to incomplete. This is a comparison of
     * the equality of two arrays. Without this deferred feedback behaviour probably
     * wont work.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        if ($prevresponse === $newresponse) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * returns the response that would get full marks.
     * Used in preview mode. If this doesn't return a correct value the button
     * labeled "Fill in the correct response in the preview form will not work
     * This value gets written into the rightanswer field of the question attempts
     * table when a quiz containing this question starts
     *
     * @return string
     */
    public function get_correct_response() {
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $correctresponse = array();
        foreach ($correctplaces as $place) {
            $correctresponse['p' . $place] = 'on';
        }
        return $correctresponse;
    }
    /**
     * Not entirely sure what this does and if the param types are correct TODO
     * @param question_attempt $qa
     * @param array $options
     * @param string $component
     * @param string $filearea
     * @param array $args
     * @param boolean $forcedownload
     * @return boolean
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'answerfeedback') {
            $currentanswer = $qa->get_last_qt_var('answer');
            $answer = $this->get_matching_answer(array('answer' => $currentanswer));
            $answerid = reset($args); // Itemid is answer id.
            return $options->feedback && $answer && $answerid == $answer->id;
        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else if ($component == 'qtype_wordselect' && $filearea == 'introduction') {
            $question = $qa->get_question();
            if ($question->introduction > "") {
                return true;
            }
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }

    /**
     * Is this place correct and so get a mark if selected
     *
     * @param number $correctplaces
     * @param number $place
     * @return boolean
     */
    public function is_correct_place($correctplaces, $place) {
        if (in_array($place, $correctplaces)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * The grade for a response
     *
     * @param array $response responses, as returned by
     * {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $this->wrongresponsecount = $this->get_wrong_responsecount($correctplaces, $response);
        foreach ($correctplaces as $place) {
            if (isset($response['p' . $place])) {
                if (( $response['p' . $place] === 'on') || ( $response['p' . $place] === 'true')) {
                    $this->rightresponsecount++;
                }
            }
        }
        $wrongfraction = @($this->wrongresponsecount / count($correctplaces));
        $fraction = @($this->rightresponsecount / count($correctplaces));
        $fraction = max(0, $fraction - $wrongfraction);
        $grade = array($fraction, question_state::graded_state_for_fraction($fraction));
        return $grade;
    }

    /**
     *
     * Not called in interactive mode
     *
     * @param array $responses
     * @param int $totaltries doesn't seem to be used
     * @return int
     */
    public function compute_final_grade($responses, $totaltries) {
        $totalscore = 0;
        $correctplaces = $this->get_correct_places($this->questiontext, $this->delimitchars);
        $wrongresponsecount = $this->get_wrong_responsecount($correctplaces, $responses[0]);
        foreach ($correctplaces as $place) {
            $lastwrongindex = -1;
            $finallyright = false;
            foreach ($responses as $i => $response) {
                if (!array_key_exists(('p' . $place), $response)) {
                    $lastwrongindex = $i;
                    $finallyright = false;
                    continue;
                } else {
                    $finallyright = true;
                }
            }
            if ($finallyright) {
                $totalscore += max(0, 1 - ($lastwrongindex + 1) * $this->penalty);
            }
        }
        $wrongfraction = @($wrongresponsecount / count($correctplaces));
        $totalscore = $totalscore / count($correctplaces);
        $totalscore = max(0, $totalscore - $wrongfraction);
        return $totalscore;
    }

    /**
     * Used when calculating the final grade
     * @param array $correctplaces
     * @param array $responses
     * @return int
     */
    public function get_wrong_responsecount($correctplaces, $responses) {
        $wrongresponsecount = 0;
        foreach ($responses as $key => $value) {
            /* chop off the leading p */
            $place = substr($key, 1);
            /* if its not in the correct places and it is turned on */
            if (!in_array($place, $correctplaces) && ($value == 'on')) {
                $wrongresponsecount++;
            }
        }
        return $wrongresponsecount;
    }

}
