<?php
/**
 * Created by PhpStorm.
 * User: gnat
 * Date: 01/11/16
 * Time: 2:18 PM
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MarkdownEditorType extends AbstractType
{
    /**
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
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

    /**
     * {@inheritdoc}
     */
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

        foreach (['indentWithTabs', 'lineWrapping', 'styleSelectedText'] as $defaultTrueOption) {
            if (isset($options[$defaultTrueOption]) && $options[$defaultTrueOption] === false) {
                $editor_config[$defaultTrueOption] = false;
            }
        }

        foreach (['autofocus', 'promptURLs','spellChecker','forceSync'] as $defaultFalseOption) {
            if (isset($options[$defaultFalseOption]) && $options[$defaultFalseOption] === true) {
                $editor_config[$defaultFalseOption] = true;
            }
        }

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
