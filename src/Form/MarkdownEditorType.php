<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;


// https://github.com/NobletSolutions/SimpleMDEBundle/blob/master/src/Form/Types/MarkdownEditorType.php
class MarkdownEditorType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefined([
            'autofocus',
            'autosave',
            'blockStyles',
            'forceSync',
            'hideIcons',
            'indentWithTabs',
            'insertTexts',
            'lineWrapping',
            'parsingConfig',
            'placeholder',
            'previewRender',
            'promptURLs',
            'renderingConfig',
            'shortcuts',
            'showIcons',
            'spellChecker',
            'status',
            'styleSelectedText',
            'tabSize',
            'toolbar',
            'toolbarTips',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $editor_config = [];

        if (isset($options['hideIcons'])) {
            $editor_config['hideIcons'] = $options['hideIcons'];
        }

        if (isset($options['placeholder'])) {
            $editor_config['placeholder'] = $options['placeholder'];
        }

        if (isset($options['showIcons'])) {
            $editor_config['showIcons'] = $options['showIcons'];
        }

        if (isset($options['tabSize']) && $options['tabSize'] !== 2) {
            $editor_config['tabSize'] = $options['tabSize'];
        }

        foreach (['indentWithTabs', 'lineWrapping', 'styleSelectedText'] as $defaultTrueOption) { // removed 'spellChecker'
            if (isset($options[$defaultTrueOption]) && $options[$defaultTrueOption] === false) {
                $editor_config[$defaultTrueOption] = false;
            }
        }

        foreach (['autofocus', 'forceSync', 'promptURLs', 'spellChecker'] as $defaultFalseOption) { // added 'spellChecker'
            if (isset($options[$defaultFalseOption]) && $options[$defaultFalseOption] === true) {
                $editor_config[$defaultFalseOption] = true;
            }
        }

        // added
        if (!isset($options['spellChecker']) || $options['spellChecker'] === false)
            $editor_config['spellChecker'] = false;
        if (!isset($options['forceSync']))
            $editor_config['forceSync'] = true;

        $view->vars['editor_config'] = json_encode($editor_config);
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'simplemde_editor';
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return TextareaType::class;
    }
}
