<?php
/**
 * Created by PhpStorm.
 * User: zhangyuzhi
 * Date: 2018/1/15
 * Time: 20:17
 * 
 * Luther Removed broken fenced code language functions, 2022-08-13
 */

require_once 'ParsedownExtra.php';

class ParsedownKatex extends ParsedownExtra
{
    function __construct()
    {
        parent::__construct();
        $this->BlockTypes['$'][] = 'Katex';
        $this->InlineTypes['$'][] = 'Katex';
        $this->inlineMarkerList = $this->inlineMarkerList . '$';
        $this->specialCharacters[] = '$';
    }

    function text($text)
    {
        $markup = parent::text($text);

        return $markup;
    }

    protected function blockKatex($Line, $Block = null)
    {
        if (preg_match('/^[ \t]*['.$Line['text'][0].']{2,}[ \t]*$/', $Line['text']))
        {
            $Block = array(
                'char' => $Line['text'][0],
                'element' => array(
                    'name' => 'katex',
                    'text' => '',
                ),
            );

            return $Block;
        }
    }

    protected function blockKatexContinue($Line, $Block)
    {
        if (isset($Block['complete']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            $Block['element']['text'] .= "\n";

            unset($Block['interrupted']);
        }

        if (preg_match('/^[ \t]*['.$Block['char'].']{2,}[ \t]*$/', $Line['text']))
        {
            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockKatexComplete($Block)
    {
        $text = $Block['element']['text'];

        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        $Block['element']['text'] = $text;

        return $Block;
    }

    protected function inlineKatex($Excerpt)
    {
        $marker = $Excerpt['text'][0];
        if (preg_match('/^(\\'.$marker.'+)[ ]*(.+?)[ ]*(?<!\\'.$marker.')\1(?!\\'.$marker.')/s', $Excerpt['text'], $matches))
        {
            $text = $matches[2];
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
            $text = preg_replace("/[ ]*\n/", ' ', $text);

            $name = 'katex';
            if ($matches[1] === '$') {
                $name = 'katex-inline';
            }
            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => $name,
                    'text' => $text,
                ),
            );
        }
    }
}
