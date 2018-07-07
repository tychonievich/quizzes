<?php
/**
 * Parses questions in the format used for my exam generation tool:

Question:
display text in markdown format
as many lines as you want, none looking like an answer

newlines OK

a. multiple choice with letter-dot preceeding
*k. asterisk in front of correct answer

-or-

*free-text correct answer

Multiquestion:
lead-in text

Subquestion:
exactly like Question but attached to Multiquestion


 * Anything in a \key{...} will be removed in display, shown after fact
 * 
 * Additionally assumes headers in form

open: date
close: date
hours: float
title: text
comments: boolean

 * 
 * The set of header items is not constrained by this file; see index.php and quiz.php for which ones are used
 */


function beginsWith( $str, $sub ) {
   return ( substr( $str, 0, strlen( $sub ) ) === $sub );
}


/**
 * Returns just the header lines as an array by default; 
 * if given an array as a second parameter, adds questions to it too
 */
function qparse($filename, &$questions=null) {
    $fh = fopen($filename, 'rb');
    if (!$fh) { return array('error'=>'file "'.$filename.'" not found'); }
    
    $headers = array();
    // read headers
    do {
        $line = trim(fgets($fh));
        if ($line === '') break;
        $kv = explode(":", $line, 2);
        if (count($kv) != 2) return array('error'=>'Malformed header line "'.$line.'"');
        $headers[trim($kv[0])] = trim($kv[1]);
    } while(true);
    
    if($questions === null) {
        fclose($fh);
        return $headers;
    }
    
    $mqs = array();
    $mq = array();
    $parts = array();
    $sq = False;
    
    $qnum = 0;
    $anum = 0;
    
    while(($line = fgets($fh)) !== FALSE) {
        if (beginsWith($line, 'Question')) {
            $qnum += 1;
            $anum = 0;
            if ($sq !== False) $parts[] = $sq;
            $sq = array('text'=>'', 'subpart'=>false, 'checkbox'=>false, 
                'slug' => substr(sha1($filename." ".$qnum), 32),
            );
            if (strpos($line, 'mmc') > 0) $sq['checkbox'] = true;
            if (strpos($line, 'free') > 0) $sq['free'] = true;
            $ptspos = stripos($line, ' points)');
            if ($ptspos > 0) {
                $ptstart = strrpos($line, '(')+1;
                $sq['points'] = floatval(substr($line, $ptstart, $ptspos-$ptstart));
            } else {
                $sq['points'] = 1.0;
            }
            if($parts !== False) $mq['parts'] = $parts;
            $parts = array();
            if($mq !== False) array_push($mqs, $mq);
            $mq = array();
        } else if (beginsWith($line, 'Multiquestion')) {
            if ($sq !== False) $parts[] = $sq;
            $sq = False;
            if($parts !== False) $mq['parts'] = $parts;
            $parts = array();
            if($mq !== False) array_push($mqs, $mq);
            $mq = array('text'=>'');
        } else if (beginsWith($line, 'Subquestion')) {
            $qnum += 1;
            $anum = 0;
            if ($sq !== False) $parts[] = $sq;
            $sq = array('text'=>'', 'subpart'=>true, 'checkbox'=>false,
                'slug' => substr(sha1($filename." ".$qnum), 32),
            );
            if (strpos($line, 'mmc') > 0) $sq['checkbox'] = true;
            if (strpos($line, 'free') > 0) $sq['free'] = true;
            $ptspos = stripos($line, ' points)');
            if ($ptspos > 0) {
                $ptstart = strrpos($line, '(');
                $sq['points'] = floatval(substr($line, $ptstart, $ptspos-$ptstart));
            } else {
                $sq['points'] = 1.0;
            }
        } else if ($sq !== False && preg_match('/^\*?[a-zX]\./', $line)) {
            $anum += 1;
            $sq['choices'][] = array(
                'text' => trim(explode('.',$line,2)[1])."\n",
                'correct' => $line[0] == '*',
                'remove' => $line[0] == 'x' || ($line[0] == '*' && $line[1] == 'x'),
                'free' => $line[0] == 'X' || ($line[0] == '*' && $line[1] == 'X'),
                'slug' => substr(sha1($filename." ".$qnum." ".$anum),32),
            );
        } else if ($sq !== False && beginsWith($line, 'key: ')) {
            $sq['solution'] = trim(substr($line, 4));
        } else if ($sq !== False && array_key_exists('choices', $sq) && count($sq['choices']) > 0) {
            $sq['choices'][count($sq['choices'])-1]['text'] .= $line;
        } else if ($sq !== False) {
            $sq['text'] .= $line;
        } else if ($mq !== False) {
            if (array_key_exists('text', $mq)) { $mq['text'] .= $line; }
            else { $mq['text'] = $line; }
        } else {
            return array('error'=>'Unparsable line "'.$line.'"');
        }
    }
    if($sq !== False) $parts[] = $sq;
    if($parts !== False) $mq['parts'] = $parts;
    if($mq !== False) array_push($mqs, $mq);


    fclose($fh);
    $questions = $mqs;
    return $headers;
}

?>
