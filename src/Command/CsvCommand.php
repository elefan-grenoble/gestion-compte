<?php
// src/App/Command/CsvCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class CsvCommand extends Command
{
    private $_neededFields;

    const DEFAULT_LABEL = '-- N/A --';

    /**
     * @param string $file
     * @param string $delimiter
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function checkDelimiter($file,$delimiter,InputInterface $input,OutputInterface $output,$nbOfColumn = 2){
        $f = fopen($file, 'r');
        $line = fgets($f);
        fclose($f);
        $chosen = false;
        while (!$chosen){
            $count = substr_count($line, $delimiter);
            if ($count <= $nbOfColumn){
                $output->writeln('<fg=red>Hum, delimiter </><fg=cyan>'.$delimiter.'</><fg=red> does not seams to be the good one</>');
                $output->writeln('<info>First line is : <fg=yellow>'.$line.'</></info>');

                $helper = $this->getHelper('question');
                $confirm = new ConfirmationQuestion('Change it ? ', true);

                if (!$helper->ask($input, $output, $confirm)) {
                    $chosen = true;
                }else{
                    $possible_values = array(',',';',"\t");
                    $default = '';
                    foreach (count_chars($line, 1) as $i => $val) {
                        if (in_array(chr($i),$possible_values)){
                            $default = chr($i);
                        }
                    }
                    $question = new Question('New delimiter: ',$default);
                    $delimiter = $helper->ask($input, $output, $question);
                    $output->writeln('Using: ' . $delimiter);
                }
            }else{
                $chosen = true;
            }
        }
        return $delimiter;
    }

    /**
     * @param string $file
     * @param string $delimiter
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function mapField($file,$delimiter,InputInterface $input,OutputInterface $output){
        $fields = $this->getNeededFields();
        $head = array();
        $lines = array();
        $row = 0;
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                $row++;
                if (10 <= $row){
                    break;
                }
                $data = array_map("utf8_encode", $data); //utf8
                if ($row == 1) {
                    $head = $data;
                }else{
                    $lines[] = $data;
                }
            }
        }
        $helper = $this->getHelper('question');

        $used_keys = array();
        foreach ($fields as $key => $value){

            $chooses = $head;
            foreach ($used_keys as $k){
                unset($chooses[$k]);
            }
            array_push( $chooses,self::DEFAULT_LABEL);
            $output->writeln('<question>Please select witch field is user for <fg=cyan;bg=black> '.$value['label'].' </></question>');
            if (isset($value['tips'])){
                $output->writeln('<fg=cyan;>'.$value['tips'].' </>');
            }
            if (count($chooses)>6){
                usleep(0.8*1000000);
            }
            $default = array_search(self::DEFAULT_LABEL,$chooses);
            if (isset($value['index']))
                $default = $value['index'];
            $question = new ChoiceQuestion(
                'Select the field',
                $chooses,
                $default
            );
            $question->setErrorMessage('field %s is invalid.');

            $field_index = -1;
            $chosen = false;
            while (!$chosen) {
                $field = $helper->ask($input, $output, $question);
                $field_index = array_search($field, $head);
                if ($field_index===false) {
                    if (isset($value['required'])&&!$value['required']){
                        $chosen = true;
                        $field_index = -1;
                    }else{
                        $output->writeln('<error>this field is required</error>');
                    }
                } else {
                    $output->writeln('You have just selected: ' . $field . ' [' . $field_index . ']');
                    $r = array();
                    foreach ($lines as $line) {
                        $r[] = $line[$field_index];
                    }
                    $output->writeln('<fg=cyan>'.implode(',',$r).'</>');
                    $helper = $this->getHelper('question');
                    $confirm = new ConfirmationQuestion('Are this data related to <fg=magenta>' . $value['label'] . '</> ? ', true);

                    if ($helper->ask($input, $output, $confirm)) {
                        $chosen = true;
                    }
                }
            }
            $fields[$key]['index'] = $field_index;
            $used_keys[] = $field_index;
            $output->writeln(['','']);
        }
        $this->setNeededFields($fields); // "save"
    }

    /**
     * @param $array
     */
    protected function setNeededFields($array){
        $this->_neededFields = $array;
    }

    /**
     * @return array
     */
    protected function getNeededFields(){
        return $this->_neededFields;
    }
    /**
     * @param integer $key
     * @param array $data
     * @return null|string
     */
    protected function getField($key,$data){
        if (isset($this->getNeededFields()[$key]['index']) && $this->getNeededFields()[$key]['index'] >= 0)
            return trim(strtolower($data[$this->getNeededFields()[$key]['index']]));
        else{
            if (isset($this->getNeededFields()[$key]['default'])){
                return $this->getNeededFields()[$key]['default'];
            }else{
                return null;
            }
        }
    }

    /**
     * @param string $file
     * @return int number of line
     */
    protected function getLines($file)
    {
        $f = fopen($file, 'rb');
        $lines = 0;

        while (!feof($f)) {
            $lines += substr_count(fread($f, 8192), "\n");
        }

        fclose($f);

        return $lines;
    }
}