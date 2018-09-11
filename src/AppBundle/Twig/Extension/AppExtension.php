<?php
namespace AppBundle\Twig\Extension;

use AppBundle\Entity\SwipeCard;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use DateInterval;
use AppBundle\Entity\Task;
use Michelf\Markdown;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class AppExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('markdown', array($this, 'markdown')),
            new \Twig_SimpleFilter('json_decode', array($this, 'jsonDecode')),
            new \Twig_SimpleFilter('email_encode',array($this, 'encodeText')),
            new \Twig_SimpleFilter('priority_to_color',array($this, 'priority_to_color')),
            new \Twig_SimpleFilter('date_fr_long',array($this, 'date_fr_long')),
            new \Twig_SimpleFilter('date_fr_full',array($this, 'date_fr_full')),
            new \Twig_SimpleFilter('duration_from_minutes',array($this, 'duration_from_minutes')),
            new \Twig_SimpleFilter('qr',array($this, 'qr')),
            new \Twig_SimpleFilter('barcode',array($this, 'barcode')),
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

    public function date_fr_full(\DateTime $date)
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B %Y", $date->getTimestamp());
    }

    public function duration_from_minutes(int $minutes)
    {
        $formatted = gmdate("G\hi", abs($minutes) * 60);
        if ($minutes < 0) {
            return "-".$formatted;
        } else {
            return $formatted;
        }
    }

    public function jsonDecode($str) {
        return json_decode($str);
    }

    public function qr($text) {
        $qrCode = new QrCode();
        try {
            $qrCode
                ->setText($text)
                ->setSize(100)
                ->setPadding(0)
                ->setErrorCorrection('high')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                ->setImageType(QrCode::IMAGE_TYPE_PNG);
        } catch (\Exception $exception){
            die($exception);
        }
        return '<img src="data:'.$qrCode->getContentType().';base64,'.$qrCode->generate().'" />';
    }

    public function barcode($text){
        $barcode = new BarcodeGenerator();
        $barcode->setText(str_pad($text, SwipeCard::PADLENGTH, '0', STR_PAD_LEFT));
        $barcode->setType(BarcodeGenerator::Code128);
        $barcode->setScale(2);
        $barcode->setThickness(25);
        $barcode->setFontSize(10);
        return '<img src="data:image/png;base64,'.$barcode->generate().'" />';
    }
}
