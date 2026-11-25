<?php

return (new PhpCsFixer\Config())
    ->setRules(['encoding' => true, 'full_opening_tag' => true])
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__ . '/src'));
