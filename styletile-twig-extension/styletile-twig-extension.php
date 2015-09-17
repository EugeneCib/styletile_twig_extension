<?php

namespace TwigStyleTile;

use Twig_Environment;

class StyleTile_Twig_Extension extends \Twig_Extension
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    private $base;

    public function initRuntime(\Twig_Environment $environment)
    {
        $this->twig = $environment;
        parent::initRuntime($environment);
    }

    public function getFunctions()
    {
        return array(
            'style_tile' => new \Twig_Function_Method($this, 'style_tile_call')
        );
    }

    public function style_tile_call($current_template_file, $url)
    {
        return StyleTileCompiler::getInstance($this->twig, $this->base, $current_template_file, $url);
    }

    public function getName()
    {
        return 'style_tile_twig';
    }

    function __construct($base)
    {
        $this->base = $base;
    }


}

class StyleTileCompiler
{
    /**
     * @var array
     */
    public $html = [];

    /**
     * @var array
     */
    public $nav = [];

    /**
     * @var string
     */
    public $js = '';

    /**
     * @var Twig_Environment
     */
    private $twig;

    /**
     * @var string
     */
    private $template_dir = '';

    /**
     * @var array
     */
    private $template_files = [];

    /**
     * @var string
     */
    private $current_template_file = '';

    /**
     * Url to witch GET string will be added - for navigation
     * @var string
     */
    private $url = '';

    /**
     * @var string sub folder to which style tile will be generated
     */
    private $base = '';

    private $get_template_directory = '';
    private static $instance;

    const GET_KEY = "_styletile";

    const DIR_DELIMITER = '/';
    const URL_DELIMITER = ',';

    /**
     * @param Twig_Environment $environment
     */
    protected function __construct(Twig_Environment $environment, $base, $current_template_file, $url)
    {
        $this->twig = $environment;
        $this->current_template_file = $current_template_file;
        $this->url = $url;
        $this->base = str_replace('\\', self::DIR_DELIMITER, $base);
        $this->init();
    }

    /**
     * @param Twig_Environment $environment
     * @param $exclude_template
     * @return mixed
     */
    public static function getInstance(\Twig_Environment $environment, $base, $current_template_file, $url)
    {
        if (null === static::$instance)
        {
            static::$instance = new static($environment, $base, $current_template_file, $url);
        }
        return static::$instance;
    }


    public function init()
    {
        try
        {
            $loader = $this->twig->getLoader();
            $paths = $loader->getPaths();

            $this->current_template_file = str_replace('\\', self::DIR_DELIMITER, $this->current_template_file);
            // COPY IDEA FROM TWIG Filesystem.php function find template
            foreach ($paths as $path)
            {
                $path = str_replace('\\', self::DIR_DELIMITER, $path);
                if (is_file($path.self::DIR_DELIMITER.$this->current_template_file))
                {
                    if (false !== $realpath = realpath($path.self::DIR_DELIMITER.$this->current_template_file))
                    {
                        $realpath = str_replace('\\', self::DIR_DELIMITER, $realpath);
                        $search = self::DIR_DELIMITER.$this->current_template_file;
                        $this->template_dir = str_replace($search, '', $realpath);
                    }
                }
            }

            if($this->template_dir == '')
            {
                throw new \Exception('Can\'t find template path. Check if you set current template correctly.');
            }
        }
        catch(\Exception $e)
        {
            echo $e->getMessage();
            die();
        }

        if(isset($_GET[self::GET_KEY]))
        {
            $sub_path = urldecode($_GET[self::GET_KEY]);
            $sub_path = str_replace(self::URL_DELIMITER, self::DIR_DELIMITER, $sub_path);
            $this->get_template_directory = $sub_path;
        }

        $this->get_template_files();

        $this->render_twig_files();


    }

    private function get_template_files()
    {
        $template_directory = $this->template_dir . $this->base;
/*        if(isset($_GET[self::GET_KEY]))
        {
            $sub_path = urldecode($_GET[self::GET_KEY]);
            $sub_path = str_replace(self::URL_DELIMITER, self::DIR_DELIMITER, $sub_path);
            $template_directory .= self::DIR_DELIMITER . $sub_path;
        }

        if(is_file($template_directory))
        {
            $path = new \SplFileInfo($template_directory);
            $this->add_if_twig_file($path);
        }
        else
        {*/
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($template_directory), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $path)
        {
            $this->add_if_twig_file($path);
        }
//        }
    }

    private function check_if_twig_file(\SplFileInfo $path)
    {
        return $path->isFile() && preg_match('/^twig?$/i', pathinfo($path->getFilename(), PATHINFO_EXTENSION));
    }

    private function add_if_twig_file(\SplFileInfo $path)
    {
        if(!$this->check_if_twig_file($path)) return false;

        $template_file = str_replace($this->template_dir . self::DIR_DELIMITER, '', $path->getPathname());
        if ($template_file != $this->current_template_file)
        {
            $this->template_files[] = $template_file;
        }
    }

    private function render_twig_files()
    {
        $base_path = $this->template_dir . $this->base . self::DIR_DELIMITER;
        foreach($this->template_files as $template_file)
        {
            $template_path = $this->template_dir . self::DIR_DELIMITER . $template_file;
            $no_base_template_path = str_replace($base_path, '', $template_path);


            // check if need HTML of template
            if(empty($this->get_template_directory) || strpos($no_base_template_path, $this->get_template_directory) === 0)
            {
                $this->render_template($template_file, $template_path);
            }
            $path_structure = explode(self::DIR_DELIMITER, $no_base_template_path);
            $this->recursive_nav_generation($path_structure, $this->nav, '');


        }
    }

    private function render_template($template_file, $template_path)
    {
        $template_name = explode('.', $template_file)[0];
        $data_file = $this->template_dir . self::DIR_DELIMITER . $template_name . '.json';

        $data = $this->get_json_data($data_file);

        $tst_html = new TwigStyleTileHTML();
        $tst_html->html = $this->twig->render($template_file, $data);
        $tst_html->file_name = $template_file;
        $tst_html->path = $template_path;
        $this->html[] = $tst_html;
    }

    public function recursive_nav_generation($path_structure, &$nav, $path)
    {
        //var_dump($nav);
        if(strlen($path) > 0)
        {
            $path .= self::URL_DELIMITER;
        }
        $path .= urlencode($path_structure[0]);

        $index = str_replace('-', '', $path_structure[0]);
        if(count($path_structure) == 1) {
            $nav[$index] = [
                'title' => $path_structure[0],
                'link' => $this->link_from_path($path),
            ];
            return $nav;
        }

        if(!isset($nav[$index]))
        {
            $nav[$index] = [
                'title' => $path_structure[0],
                'link' => $this->link_from_path($path),
                'items' => [],
            ];
        }
        array_shift($path_structure);
        //var_dump($nav[$index]['items']);
        $nav[$index]['items'] = $this->recursive_nav_generation($path_structure,  $nav[$index]['items'], $path);
        return $nav;

    }


    private function link_from_path($path)
    {
        return $this->url . sprintf('/?%s=%s', self::GET_KEY, $path);
    }

    private function get_json_data($data_file)
    {
        $data = [];
        if (file_exists($data_file))
        {
            $encoded_json_data = file_get_contents($data_file);
            if($this->is_data_file_reference($encoded_json_data))
            {
                $this->get_data_file_reference($encoded_json_data);
                $data = $encoded_json_data;
            }
            else if($this->is_data_file_exec($encoded_json_data))
            {
                $this->get_data_file_exec($encoded_json_data);
                $data = (array) $encoded_json_data;
            }
            else
            {
                $data = json_decode($encoded_json_data);
                if ($data == null)
                {
                    $data = [];
                } else
                {
                    $data = (array) $data;
                }

                $this->iterate_over_data($data);
            }
        }



        return $data;
    }


    private function iterate_over_data(&$data)
    {

        if(is_object($data) || is_array($data))
        {
            foreach($data as $key => &$value)
            {
                $this->iterate_over_data($value);
            }
        }
        else if(is_string($data))
        {

            if($this->is_data_file_reference($data))
            {
                $this->get_data_file_reference($data);
            }
            else if($this->is_data_file_exec($data))
            {
                $this->get_data_file_exec($data);
            }

        }

    }

    private function is_data_file_reference($data)
    {
        return (strpos($data, 'data@') === 0);
    }

    private function is_data_file_exec($data)
    {
        return (strpos($data, 'exec@') === 0);
    }

    private function get_data_file_reference(&$data)
    {
        $nested_data_path = str_replace('data@', '', $data);
        $data_file = $this->template_dir . self::DIR_DELIMITER . $nested_data_path;
        $data = $this->get_json_data($data_file);
    }

    private function get_data_file_exec(&$data)
    {
        $exec_code = str_replace('exec@', '', $data);
        try{
            $result = eval("return " . $exec_code);
            $data = $result;
        } catch (\Exception $e)
        {
            throw new \Exception("Stylitile generator exec@ failed : " . $data . ". Original message " . $e->getMessage());
        }
    }

}

class TwigStyleTileHTML
{
    public $html = "";
    public $file_name = "";
    public $path = "";
}