<?php

/*
 * Configuration of twig-cs-fixer (https://github.com/VincentLanglet/Twig-CS-Fixer)
 * Usage: twig-cs-fixer lint interface/templates   (check)
 *        twig-cs-fixer fix interface/templates    (fix)
 * Only the indentation is checked: 4 spaces, no tabs.
 */

use TwigCsFixer\Config\Config;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Rules\Whitespace\IndentRule;

$ruleset = new Ruleset();
$ruleset->addRule(new IndentRule(4));

$config = new Config();
$config->setRuleset($ruleset);
// No .twig-cs-fixer.cache file in the repository
$config->setCacheFile(null);

return $config;
