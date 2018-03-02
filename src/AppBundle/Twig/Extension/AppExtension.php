<?php
namespace AppBundle\Twig\Extension;

use DateInterval;
use AppBundle\Entity\Task;
use Michelf\Markdown;

class AppExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('markdown', array($this, 'markdown')),
            new \Twig_SimpleFilter('email_encode',array($this, 'encodeText')),
            new \Twig_SimpleFilter('priority_to_color',array($this, 'priority_to_color')),
            new \Twig_SimpleFilter('date_fr_long',array($this, 'date_fr_long')),
            new \Twig_SimpleFilter('duration_from_minutes',array($this, 'duration_from_minutes')),
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

    public function priority_to_color($priority){
        $color = "grey";
        switch ($priority){
            case Task::PRIORITY_URGENT_VALUE :
                $color = Task::PRIORITY_URGENT_COLOR;
                break;
            case Task::PRIORITY_IMPORTANT_VALUE :
                $color = Task::PRIORITY_IMPORTANT_COLOR;
                break;
            case Task::PRIORITY_NORMAL_VALUE :
                $color = Task::PRIORITY_NORMAL_COLOR;
                break;
            case Task::PRIORITY_ANNEXE_VALUE :
                $color = Task::PRIORITY_ANNEXE_COLOR;
                break;
        }
        return $color;
    }

    public function getName()
    {
        return 'my_app_extension';
    }

    public function date_fr_long(\DateTime $date)
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B", $date->getTimestamp());
    }

    public function duration_from_minutes(int $minutes)
    {
        return date("G\hi", ($minutes - 60) * 60);
    }
}
