<?php
namespace App\Twig\Extension;

use App\Entity\AbstractRegistration;
use App\Entity\AnonymousBeneficiary;
use App\Entity\Registration;
use App\Helper\SwipeCard;
use App\Service\Picture\BasePathPicture;
use DateInterval;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Exception;
use Michelf\Markdown;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{

    private $basePathPicture;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    private $localCurrencyName;

    private $logger;

    /**
     * @var SwipeCard
     */
    private $swipeCard;

    public function __construct(
        BasePathPicture $basePathPicture,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        $localCurrencyName,
        SwipeCard $swipeCard
    ) {
        $this->basePathPicture = $basePathPicture;
        $this->entityManager = $entityManager;
        $this->localCurrencyName = $localCurrencyName;
        $this->swipeCard = $swipeCard;
        $this->logger = $logger;
    }

    public function getFilters()
    {
        return array(
            new TwigFilter('markdown', array($this, 'markdown')),
            new TwigFilter('ellipsis', array($this, 'ellipsis')),
            new TwigFilter('json_decode', array($this, 'jsonDecode')),
            new TwigFilter('email_encode', array($this, 'encodeText')),
            new TwigFilter('priority_to_color', array($this, 'priority_to_color')),
            new TwigFilter('date_fr', array($this, 'date_fr')),
            new TwigFilter('date_fr_long', array($this, 'date_fr_long')),
            new TwigFilter('date_fr_full', array($this, 'date_fr_full')),
            new TwigFilter('date_fr_with_time', array($this, 'date_fr_with_time')),
            new TwigFilter('date_fr_long_with_time', array($this, 'date_fr_long_with_time')),
            new TwigFilter('date_fr_full_with_time', array($this, 'date_fr_full_with_time')),
            new TwigFilter('date_short', array($this, 'date_short')),
            new TwigFilter('date_time', array($this, 'date_time')),
            new TwigFilter('date_w3c', array($this, 'date_w3c')),
            new TwigFilter('time_short', array($this, 'time_short')),
            new TwigFilter('duration_from_minutes', array($this, 'duration_from_minutes')),
            new TwigFilter('qr', array($this, 'qr')),
            new TwigFilter('barcode', array($this, 'barcode')),
            new TwigFilter('vigenere_encode', array($this, 'vigenere_encode')),
            new TwigFilter('vigenere_decode', array($this, 'vigenere_decode')),
            new TwigFilter('recall_date', array($this, 'get_recall_date')),
            new TwigFilter('img', array($this, 'imgFilter')),
            new TwigFilter('payment_mode_devise', array($this, 'payment_mode_devise')),
            new TwigFilter('payment_mode', array($this, 'payment_mode')),
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

    public function ellipsis($text, $maxLen = 50, $ellipsis = '…')
    {
        if (strlen($text) <= $maxLen)
            return $text;
        return substr($text, 0, $maxLen-strlen($ellipsis)).$ellipsis;
    }

    public function encodeText($text)
    {
        $encoded_text = '';

        for ($i = 0; $i < strlen($text); $i++)
        {
            $char = $text[$i];
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

    /**
     * Exemple: "29 juin 2022"
     */
    public function date_fr(\DateTime $date)
    {
        return strftime("%e %B %Y", $date->getTimestamp());
    }

    /**
     * Example: "mercredi 29 juin"
     */
    public function date_fr_long(\DateTime $date)
    {
        return strftime("%A %e %B", $date->getTimestamp());
    }

    /**
     * Example: "mercredi 29 juin 2022"
     */
    public function date_fr_full(\DateTime $date)
    {
        return strftime("%A %e %B %Y", $date->getTimestamp());
    }

    /**
     * Example: "29 juin 2022 à 9h30"
     */
    public function date_fr_with_time(\DateTime $date)
    {
        return strftime("%e %B %Y à %kh%M", $date->getTimestamp());
    }

    /**
     * Example: "mercredi 29 juin à 9h30"
     * 
     * Note: not used
     */
    public function date_fr_long_with_time(\DateTime $date)
    {
        return strftime("%A %e %B à %kh%M", $date->getTimestamp());
    }

    /**
     * Example: "mercredi 29 juin 2022 à 9h30"
     *
     * @param: \DateTime|\DateTimeImmutable $date
     */
    public function date_fr_full_with_time($date)
    {
        return strftime("%A %e %B %Y à %kh%M", $date->getTimestamp());
    }

    /**
     * Example: "29/06/22"
     */
    public function date_short($date)  # \DateTime or \DateTimeImmutable
    {
        return strftime("%d/%m/%Y", $date->getTimestamp());
    }

    /**
     * Example: "29/06/22 9h30"
     */
    public function date_time(\DateTime $date)
    {
        return strftime("%d/%m/%Y %kh%M", $date->getTimestamp());
    }

    /**
     * Example: "2022-06-29T09:30:18+02:00"
     */
    public function date_w3c(\DateTime $date)
    {
        return $date->format(\DateTimeInterface::W3C);
    }

    /**
     * Example: "9h30" ; "10h"
     */
    public function time_short(\DateTime $date)
    {
        $time_minutes = $date->format('i');
        if ($time_minutes == "00") {
            return strftime("%kh", $date->getTimestamp());
        }
        return strftime("%kh%M", $date->getTimestamp());
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
        $hours = intdiv(abs($minutes), 60);
        $remaining_minutes = sprintf("%02d", abs($minutes) % 60);
        $formatted = $hours.'h'.$remaining_minutes;
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
        $qrCode = new QrCode($text);
        try {
            $qrCode->setSize(200)
                ->setMargin(0)
                ->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH)
                ->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0])
                ->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255]);

            return '<img src="data:'.$qrCode->getContentType().';base64,'.base64_encode($qrCode->writeString()).'" />';
        } catch (Exception $exception) {
            $this->logger->error('QR Code generation error: '.$exception->getMessage());
        }
    }

    public function barcode($text){
        $generator = new BarcodeGeneratorPNG();
        try {
            $barcode = $generator->getBarcode($text, $generator::TYPE_EAN_13, 2, 25);
            return '<img src="data:image/png;base64,'.base64_encode($barcode).'" />';
        } catch (Exception $exception) {
            $this->logger->error('Barcode generation error: '.$exception->getMessage());
        }
    }

    public function vigenere_encode($text){
        return $this->swipeCard->vigenereEncode($text);
    }

    public function vigenere_decode($text){
        return $this->swipeCard->vigenereDecode($text);
    }

    public function get_recall_date(AbstractRegistration $ar){
        if ($ar->getType() == AbstractRegistration::TYPE_ANONYMOUS){
            $anonyB = $this->entityManager->getRepository(AnonymousBeneficiary::class)->find($ar->getEntityId());
            if ($anonyB){
                return $anonyB->getRecallDate();
            }
        }
        return null;
    }
}
