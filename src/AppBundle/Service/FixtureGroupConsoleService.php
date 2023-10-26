<?php

namespace AppBundle\Service;

use Symfony\Component\Console\Input\InputInterface;

class FixtureGroupConsoleService
{

    private $input;

    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Returns the groups passed as options to the command
     * @return array
     */
    public function getGroups(): array
    {
        return $this->input ? $this->input->getOption('group') : [];
    }

}

