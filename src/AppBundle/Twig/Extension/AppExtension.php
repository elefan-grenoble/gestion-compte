<?php
namespace AppBundle\Twig\Extension;

use AppBundle\Entity\AbstractRegistration;
use AppBundle\Entity\AnonymousBeneficiary;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Registration;
use AppBundle\Entity\Shift;
use AppBundle\Entity\SwipeCard;
use AppBundle\Service\Picture\BasePathPicture;
use CodeItNow\BarcodeBundle\Utils\BarcodeGenerator;
use CodeItNow\BarcodeBundle\Utils\QrCode;
use DateInterval;
use AppBundle\Entity\Task;
use Michelf\Markdown;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{

    private $container;
    private $basePathPicture;

    public function __construct(Container $container, BasePathPicture $basePathPicture) {
        $this->container = $container;
        $this->basePathPicture = $basePathPicture;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('markdown', array($this, 'markdown')),
            new TwigFilter('json_decode', array($this, 'jsonDecode')),
            new TwigFilter('email_encode',array($this, 'encodeText')),
            new TwigFilter('priority_to_color',array($this, 'priority_to_color')),
            new TwigFilter('date_fr_long',array($this, 'date_fr_long')),
            new TwigFilter('date_fr_full',array($this, 'date_fr_full')),
            new TwigFilter('date_fr_with_time',array($this, 'date_fr_with_time')),
            new TwigFilter('date_time',array($this, 'date_time')),
            new TwigFilter('date_w3c',array($this, 'date_w3c')),
            new TwigFilter('duration_from_minutes',array($this, 'duration_from_minutes')),
            new TwigFilter('qr',array($this, 'qr')),
            new TwigFilter('barcode',array($this, 'barcode')),
            new TwigFilter('vigenere_encode',array($this, 'vigenere_encode')),
            new TwigFilter('vigenere_decode',array($this, 'vigenere_decode')),
            new TwigFilter('recall_date',array($this, 'get_recall_date')),
            new TwigFilter('img',array($this, 'imgFilter')),
            new TwigFilter('payment_mode_devise',array($this, 'payment_mode_devise')),
            new TwigFilter('payment_mode',array($this, 'payment_mode')),
        );
    }

    public function imgFilter( $entity,$fileField, $filter)
    {
        return $this->basePathPicture->getPicturePath($entity,$fileField, $filter);
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

    public function date_time(\DateTime $date)
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%D %H:%M", $date->getTimestamp());
    }

    public function date_fr_full(\DateTime $date)
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B %Y", $date->getTimestamp());
    }

    public function date_fr_with_time(\DateTime $date)
    {
        setlocale(LC_TIME, 'fr_FR.UTF8');
        return strftime("%A %e %B %Y à %H:%M", $date->getTimestamp());
    }

    public function date_w3c(\DateTime $date)
    {
        return $date->format( \DateTimeInterface::W3C);
    }

    public function payment_mode_devise(int $value)
    {
        $name = "€";
        switch ($value){
            case Registration::TYPE_CREDIT_CARD :
                $name = '€ en CARTE CREDIT';
                break;
            case Registration::TYPE_LOCAL :
                $name = $this->container->getParameter('local_currency_name');
                break;
            case Registration::TYPE_CASH :
                $name = '€ en ESPECE';
                break;
            case Registration::TYPE_CHECK :
                $name = '€ en CHEQUE';
                break;
            case Registration::TYPE_HELLOASSO :
                $name = '€ HelloAsso';
                break;
        }
        return $name;
    }

    public function payment_mode(int $value)
    {
        $name = "€ ";
        switch ($value){
            case Registration::TYPE_CREDIT_CARD :
                $name .= 'carte';
                break;
            case Registration::TYPE_LOCAL :
                $name = $this->container->getParameter('local_currency_name');
                break;
            case Registration::TYPE_CASH :
                $name .= 'espèce';
                break;
            case Registration::TYPE_CHECK :
                $name .= 'chèque';
                break;
            case Registration::TYPE_DEFAULT :
                $name .= 'autre';
                break;
            case Registration::TYPE_HELLOASSO :
                $name .= 'HelloAsso';
                break;
        }
        return $name;
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
                ->setSize(200)
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
        $barcode->setText($text);
        $barcode->setType(BarcodeGenerator::Ean13);
        $barcode->setScale(2);
        $barcode->setThickness(25);
        $barcode->setFontSize(10);
        return '<img src="data:image/png;base64,'.$barcode->generate().'" />';
    }

    public function vigenere_encode($text){
        return $this->container->get('AppBundle\Helper\SwipeCard')->vigenereEncode($text);
    }

    public function vigenere_decode($text){
        return $this->container->get('AppBundle\Helper\SwipeCard')->vigenereDecode($text);
    }

    public function get_recall_date(AbstractRegistration $ar){
        if ($ar->getType() == AbstractRegistration::TYPE_ANONYMOUS){
            $em = $this->container->get('doctrine')->getManager();
            $anonyB = $em->getRepository(AnonymousBeneficiary::class)->find($ar->getEntityId());
            if ($anonyB){
                return $anonyB->getRecallDate();
            }
        }
        return null;
    }
}
