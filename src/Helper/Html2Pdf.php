<?php
namespace App\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class Html2Pdf {

    private $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }
    /**
     * @var \Spipu\Html2Pdf\Html2Pdf $_pdf;
     */
    private $_pdf;

    public function create($orientation = 'P', $format = 'A4', $lang = 'fr', $unicode = true, $encoding = 'UTF-8', $margins = array(5, 5, 5, 8), $pdfa = false){
        $this->_pdf = new \Spipu\Html2Pdf\Html2Pdf( $orientation, $format, $lang, $unicode, $encoding, $margins, $pdfa);
    }

    public function generatePdf($template,$name){
        $this->_pdf->writeHTML($template);
        return $this->_pdf->Output($name.'.pdf');
    }
}