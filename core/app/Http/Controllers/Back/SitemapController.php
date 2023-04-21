<?php

namespace App\Http\Controllers\Back;

use App\Models\Sitemap;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Sitemap\SitemapGenerator;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('adminlocalize');
    }

    public function index(Request $request)
    {
        $data['sitemaps'] = Sitemap::orderBy('id', 'DESC')->paginate(10);
        return view('back.settings.sitemap.index', $data);
    }

    public function add()
    {
        return view('back.settings.sitemap.add');
    }

    public function download(Request $request) {

        return response()->download('assets/sitemaps/'.$request->filename);
    }

    public function store(Request $request)
    {
        $data = new Sitemap();
        $input = $request->all();

        $filename = 'sitemap' . uniqid() . '.xml';
        SitemapGenerator::create($request->sitemap_url)->writeToFile('assets/sitemaps/' . $filename);
        $input['filename']    = $filename;
        $input['sitemap_url'] = $request->sitemap_url;
        $data->fill($input)->save();

        return redirect()->route('admin.sitemap.index')->withSuccess(__('Sitemap Generate Successfully'));

    }

    public function delete($id)
    {

        $sitemap = Sitemap::find($id);
        @unlink('assets/sitemaps/' . $sitemap->filename);
        $sitemap->delete();

        return redirect()->back()->withSuccess(__('Sitemap file deleted successfully!'));

    }

    public function generateSiteMap(Request $request)
    {
        $files = array();
        $options = [
            "directories" => [__DIR__."/../../../../../assets"],
            "absPath" => realpath(__DIR__."/../../../../../"),
            "appURL" => env("APP_URL")
        ];
        $this->readDirectoryContent($options, $files);
        
        $steMapStoragePath = realpath( __DIR__."/../../../../../assets/sitemaps/");
        $filename = 'sitemap-' . uniqid() . '.xml';
        $xmlSiteMapPath = $steMapStoragePath."/".$filename;
        $xmlSiteMap = fopen( $xmlSiteMapPath, "x+");

        fwrite($xmlSiteMap, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        fwrite($xmlSiteMap, "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n");
        foreach ($files ?? [] as $file) {
            fwrite($xmlSiteMap, "\t<url>\n");
                fwrite($xmlSiteMap, "\t\t<loc>". $file["path"] ."</loc>\n");
                fwrite($xmlSiteMap, "\t\t<filename>". $file["filename"] ."</filename>\n");
                fwrite($xmlSiteMap, "\t\t<lastmod>". date("Y-m-d H:i:s") ."</lastmod>\n");
            fwrite($xmlSiteMap, "\t</url>\n");
        }
        fwrite($xmlSiteMap, "</urlset>\n");
        fclose($xmlSiteMap);

        Sitemap::truncate();
        $sitemap = new Sitemap();
        $sitemap->sitemap_url = $options["appURL"]."/assets/sitemaps/".$filename;
        $sitemap->filename = $filename;
        $sitemap->save();

        return redirect()->back()->withSuccess(__('Sitemap links updated successfully!'));
    }

    private function readDirectoryContent($options, &$files)
    {
        try 
        {
            foreach ($options["directories"] as $currentDir) 
            {
                $dirContent = scandir($currentDir);
                foreach ($dirContent as $subDirOrFile) 
                {
                    $path = realpath($currentDir . DIRECTORY_SEPARATOR . $subDirOrFile);
                    if (!is_dir($path)) 
                    {
                        $files[] = [
                            "filename" => basename($path),
                            "path" => str_replace($options["absPath"], $options["appURL"], $path),
                        ];
                    } 
                    else if ($subDirOrFile != "." && $subDirOrFile != "..") 
                    {
                        $this->readDirectoryContent([
                            "directories" => [$path],
                            "appURL" => $options["appURL"],
                            "absPath" => $options["absPath"],
                        ], $files);

                        // Exclue folders
                        // $files[] = $path;
                    }
                }
            }
        } 
        catch (\Throwable $th) 
        {
            throw $th;
        }
    }

}
