<?php
namespace AppBundle\Twig;

use Michelf\Markdown;

class AppExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('markdown', array($this, 'markdown')),
            new \Twig_SimpleFilter('email_encode',array($this, 'encodeText')),
        );
    }

    public function markdown($markdown)
    {
        $html = Markdown::defaultTransform($markdown);
        return $html;
    }

    public function encodeText($text)
    {
        $encoded_text = '';

        for ($i = 0; $i < strlen($text); $i++)
        {
            $char = $text{$i};
            $r = rand(0, 100);

            # roughly 10% raw, 45% hex, 45% dec
            # '@' *must* be encoded. I insist.
            if ($r > 90 && $char != '@')
            {
                $encoded_text .= $char;
            }
            else if ($r < 45)
            {
                $encoded_text .= '&#x' . dechex(ord($char)) . ';';
            }
            else
            {
                $encoded_text .= '&#' . ord($char) . ';';
            }
        }

        return $encoded_text;
    }

    public function getName()
    {
        return 'app_extension';
    }
}