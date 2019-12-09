<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @author  Thomas Körner <t.koerner@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */


namespace HeimrichHannot\EncoreBundle\Asset;


use Contao\LayoutModel;
use Contao\PageModel;
use Twig\Environment;

class TemplateAsset
{
    /**
     * @var Environment
     */
    private $twig;
    /**
     * @var LayoutModel
     */
    private $layout;
    /**
     * @var PageEntrypoints
     */
    private $pageEntrypoints;
    /**
     * @var PageModel
     */
    private $page;
    /**
     * @var string|null
     */
    private $entriesField;
    /**
     * @var bool
     */
    private $initialized = false;
    /**
     * @var array
     */
    private $templateData;
    /**
     * @var array
     */
    private $bundleConfig;
    /**
     * @var string
     */
    private $webDir;

    public function __construct(array $bundleConfig, string $webDir, Environment $twig, PageEntrypoints $pageEntrypoints)
    {
        $this->twig = $twig;
        $this->pageEntrypoints = $pageEntrypoints;
        $this->bundleConfig = $bundleConfig;
        $this->webDir = $webDir;
    }

    public function createInstance(PageModel $pageModel, LayoutModel $layoutModel, ?string $entriesField = null)
    {
        $instance = new static($this->bundleConfig, $this->webDir, $this->twig, $this->pageEntrypoints);
        $instance->initialize($pageModel, $layoutModel, $entriesField);
        return $instance;
    }

    public function initialize(PageModel $pageModel, LayoutModel $layoutModel, ?string $entriesField = null)
    {
        $this->page = $pageModel;
        $this->layout = $layoutModel;
        $this->entriesField = $entriesField;
        $this->pageEntrypoints = $this->pageEntrypoints->createInstance();
        $this->templateData = $this->layout->row();

        if (!$this->pageEntrypoints->generatePageEntrypoints($this->page, $this->layout, $this->entriesField))
        {
            return;
        }

        $this->templateData['jsEntries'] = $this->pageEntrypoints->getJsEntries();
        $this->templateData['jsHeadEntries'] = $this->pageEntrypoints->getJsHeadEntries();
        $this->templateData['cssEntries'] = $this->pageEntrypoints->getCssEntries();

        $this->initialized = true;
    }

    private function generateTags(string $layoutField, string $defaultTemplate)
    {
        if (!$this->initialized) {
            throw new \Exception("TemplateAsset not initialized!");
        }

        return $this->twig->render(
            $this->getItemTemplateByName($this->layout->{$layoutField} ?: $defaultTemplate), $this->templateData
        );
    }

    /**
     * Return the javascript that should be included in the header region
     *
     * @return string
     * @throws \Exception
     */
    public function headScriptTags(): string
    {
        return $this->generateTags('encoreScriptsImportsTemplate', 'default_head_js');
    }

    /**
     * Return the javascript tags that should be included in the footer region
     *
     * @return string
     * @throws \Exception
     */
    public function scriptTags(): string
    {
        return $this->generateTags('encoreScriptsImportsTemplate', 'default_js');
    }

    /**
     * Return the css link tags that should be included in the header region
     *
     * @return string
     * @throws \Exception
     */
    public function linkTags()
    {
        return $this->generateTags('encoreStylesheetsImportsTemplate', 'default_css');
    }

    /**
     * Return a link tag with inline css
     *
     * @return bool|string
     * @throws \Exception
     */
    public function inlineCssLinkTag()
    {
        $styleTags = $this->linkTags();

        preg_match_all('@<link rel="stylesheet" href="([^"]+)">@i', $styleTags, $matches);

        if (isset($matches[1]) && !empty($matches[1])) {
            $inlineCss = implode("\n", array_map(function ($path) {
                return file_get_contents($this->webDir.preg_replace('@<link rel="stylesheet" href="([^"]+)">@i', '$1', $path));
            }, $matches[1]));

            return $inlineCss;
        }

        return false;
    }

    protected function getItemTemplateByName(string $name)
    {
        if (!isset($this->bundleConfig['templates']['imports'])) {
            return null;
        }

        $templates = $this->bundleConfig['templates']['imports'];

        foreach ($templates as $template) {
            if ($template['name'] == $name) {
                return $template['template'];
            }
        }

        return null;
    }
}