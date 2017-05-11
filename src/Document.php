<?php namespace Bugotech\Documents;

use Bugotech\IO\Filesystem;
use Illuminate\Http\Response;
use Picqer\Barcode\BarcodeGeneratorPNG;

class Document
{
    /**
     * Styles.
     * @var array
     */
    protected $styles = [];

    /**
     * Templates.
     * @var array
     */
    protected $templates = [];

    /**
     * Orientação da página.
     * @var string
     */
    protected $orientation = 'portrait';

    /**
     * Tamanho da página. (A4...).
     * @var string
     */
    protected $pageSize = 'A4';

    /**
     * Title.
     * @var string
     */
    protected $title;

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * Pages.
     *
     * @var array
     */
    protected $pages = [];

    /**
     * Document constructor.
     */
    public function __construct()
    {
        $this->files = new Filesystem();

        $this->addStyle(__DIR__ . '/../resource/style.css');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getHTML()
    {
        ob_start();
        try {
            $style = '';

            foreach ($this->styles as $styleItem) {
                $style .= file_get_contents($styleItem);
                $style .= "\n";
            }

            $content = '';
            foreach ($this->templates as $template) {
                $content .= $template;
                $content .= "\n";
            }

            $title = $this->title;

            $vars['title'] = $title;
            $vars['style'] = $style;

            $head = $this->files->getContentRequire(__DIR__ . '/head.php', $vars);

            require __DIR__ . '/template.php';

            return ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Enviar PDF para o buffer.
     *
     * @param $filename
     * @param string $disposition
     * @param null $encoding
     * @return Response
     */
    public function pdf($filename, $disposition = 'inline', $encoding = null)
    {
        $pdf = $this->getPDF($encoding);

        $response = new Response();
        $response->setContent($pdf);

        $response->headers->set('Content-Type', 'application/pdf', true);
        $response->headers->set('Content-Length', strlen($pdf), true);
        $response->headers->set('Content-Disposition', $disposition . '; filename="' . $filename . '"', true);
        $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate', true);

        return $response;
    }

    /**
     * @param null $encoding
     * @return string
     * @throws \Exception
     */
    public function getPDF($encoding = null)
    {
        require_once __DIR__ . '/bootstrap.php';

        $html = $this->getHTML();

        $html = $this->convertEntities($html);

        $pdf = new \DOMPDF();
        $pdf->set_base_path(__DIR__);
        $pdf->load_html($html);
        $pdf->set_paper($this->pageSize, $this->orientation);

        $pdf->render();

        return $pdf->output();
    }

    /**
     * Adicionar templates.
     *
     * @param $templates
     * @param array $vars
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function addContent($templates, $vars = [])
    {
        if (is_array($templates)) {
            foreach ($templates as $template) {
                $this->addContent($template, $vars);
            }
        } else {
            if ($this->files->isFile($templates)) {
                if (strtolower($this->files->extension($templates)) == 'php') {
                    ob_start();
                    try {
                        $doc = $this;

                        extract($vars);

                        include $templates;

                        $buffer = ob_get_clean();
                        $this->templates[] = $buffer;
                    } catch (\Exception $e) {
                        ob_end_clean();
                        throw $e;
                    }
                } else {
                    $this->templates[] = $this->files->get($templates);
                }
            } else {
                $this->templates[] = $templates;
            }
        }
    }

    /**
     * Adicionar styles.
     *
     * @param array $style
     */
    public function addStyle($style)
    {
        if (is_array($style)) {
            foreach ($style as $item) {
                $this->styles[] = $item;
            }
        } else {
            $this->styles[] = $style;
        }
    }

    /**
     * Add new page.
     *
     * @return bool
     */
    public function newPage()
    {
        $this->templates[] = "\n" . '<div class="page-break"></div>';

        return true;
    }

    /**
     * Inserir uma linha pontilhada.
     *
     * @param $value - Tamanho do div da linha.
     * @return bool
     */
    public function pontilhado($value)
    {
        $value = $value / 2;

        $div = '<div>';
        $div .= '<div style="height: ' . $value . 'px"></div>';
        $div .= '<div style="border-bottom: dashed 1px #000"></div>';
        $div .= '<div style="height: ' . $value . 'px"></div>';
        $div .= '</div>';

        $this->templates[] = "\n" . $div;

        return true;
    }

    /**
     * Inserir um espaço no template.
     *
     * @param $value
     * @return bool
     */
    public function space($value)
    {
        $div = '<div style="height: ' . $value . 'px"></div>';

        $this->templates[] = "\n" . $div;

        return true;
    }

    /**
     * Gerar código de barras.
     *
     * @param $valor
     * @param string $tipo
     * @return string
     */
    public function barcode($valor, $tipo = BarcodeGeneratorPNG::TYPE_CODE_128_C)
    {
        $generator = new BarcodeGeneratorPNG();
        $buffer = $generator->getBarcode($valor, $tipo, 1, 40);

        return 'data:image/png;base64,' . base64_encode($buffer);
    }

    /**
     * Decodificar a imagem para base64.
     *
     * @param $path
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function image($path)
    {
        // Verificando se imagem existe
        if (!$this->files->exists($path)) {
            throw new \Exception('Imagem não encontrada no caminho especificado!');
        }

        $ext = strtolower($this->files->extension($path));
        $buffer = $this->files->get($path);

        return 'data:image/' . $ext . ';base64,' . base64_encode($buffer);
    }

    /**
     * Setar um title para o head da página.
     *
     * @param $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Setar o tamanho da página.
     *
     * @param $pageSize
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
    }

    /**
     * Setar a orientaçãoo da página.
     *
     * @param $orientation
     */
    public function setOrientation($orientation)
    {
        $this->orientation = $orientation;
    }

    /**
     * Tratar caracteres especiais.
     *
     * @param $subject
     * @return string
     */
    protected function convertEntities($subject)
    {
        $entities = [
            //'?' => '&#0128;',
        ];

        foreach ($entities as $search => $replace) {
            $subject = str_replace($search, $replace, $subject);
        }

        return $subject;
    }
}