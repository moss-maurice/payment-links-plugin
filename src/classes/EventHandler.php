<?php

namespace mmaurice\PaymentLinksPlugin\classes;

use mmaurice\modx\Core;

class EventHandler
{
    protected $injector;
    protected $pageHandler;

    public function __construct($pageHandler)
    {
        $this->injector = new Core;
        $this->pageHandler = $pageHandler;

        $modx = $this->injector->modx();

        $url = preg_replace('/^([^\?]*)(\?.*)$/i', '$1', $_SERVER['REQUEST_URI']);

        switch ($modx->event->name) {
            case 'OnPageNotFound':
                if (preg_match('/^(?:' . str_replace('/', '\\/', (!is_null($pageHandler) ? '/' . trim(is_numeric($pageHandler) ? $modx->makeUrl($pageHandler) : $pageHandler, '/') : '')) . '\/)([^$]+)$/imu', $url, $matches)) {
                    $decoded = explode('|', @base64_decode($matches[1]));
                    $decoded = @array_combine(['amount', 'hash'], $decoded);

                    if ($decoded and (md5($decoded['amount']) === $decoded['hash'])) {
                        $this->sendForward($this->pageHandler, "{{payButton ? &amount=`{$decoded['amount']}` &tmpl=`form`}}");
                    }
                }
            break;
            default:
            break;
        }
    }

    protected function sendForward($id, $content = '')
    {
        $modx = $this->injector->modx();

        $modx->forwards = $modx->forwards - 1;
        $modx->documentIdentifier = $id;
        $modx->documentMethod = 'id';
        $modx->documentObject = $modx->getDocumentObject($modx->documentMethod, $modx->documentIdentifier, 'prepareResponse');
        $modx->documentObject['content'] = $content;
        $modx->documentName = $modx->documentObject['pagetitle'];

        if (!$modx->documentObject['template']) {
            $modx->documentContent = "[*content*]";
        } else {
            $result = $modx->db->select('content', $modx->getFullTableName("site_templates"), "id = '{$modx->documentObject['template']}'");

            if ($template_content = $modx->db->getValue($result)) {
                $modx->documentContent = $template_content;
            } else {
                $modx->messageQuit("Incorrect number of templates returned from database", $sql);
            }
        }

        $modx->minParserPasses = empty($modx->minParserPasses) ? 2 : $modx->minParserPasses;
        $modx->maxParserPasses = empty($modx->maxParserPasses) ? 10 : $modx->maxParserPasses;

        $passes = $modx->minParserPasses;

        for ($i = 0; $i < $passes; $i++) {
            if ($i == ($passes -1)) {
                $st = strlen($modx->documentContent);
            }

            if ($modx->dumpSnippets == 1) {
                $modx->snippetsCode .= "<fieldset><legend><b style ='color: #821517;'>PARSE PASS " . ($i +1) . "</b></legend><p>The following snippets (if any) were parsed during this pass.</p>";
            }

            $modx->documentOutput = $modx->documentContent;
            $modx->invokeEvent("OnParseDocument");
            $modx->documentContent = $modx->documentOutput;
            $modx->documentContent = $modx->mergeSettingsContent($modx->documentContent);
            $modx->documentContent = $modx->mergeDocumentContent($modx->documentContent);
            $modx->documentContent = $modx->mergeSettingsContent($modx->documentContent);
            $modx->documentContent = $modx->mergeChunkContent($modx->documentContent);

            if(isset($modx->config['show_meta']) && $modx->config['show_meta'] ==1) {
                $modx->documentContent = $modx->mergeDocumentMETATags($modx->documentContent);
            }

            $modx->documentContent = $modx->evalSnippets($modx->documentContent);
            $modx->documentContent = $modx->mergePlaceholderContent($modx->documentContent);
            $modx->documentContent = $modx->mergeSettingsContent($modx->documentContent);

            if ($modx->dumpSnippets == 1) {
                $modx->snippetsCode .= "</fieldset><br />";
            }

            if ($i == ($passes -1) && $i < ($modx->maxParserPasses - 1)) {
                $et = strlen($modx->documentContent);

                if ($st != $et) {
                    $passes++;
                }
            }
        }

        $modx->outputContent();

        die();
    }
}