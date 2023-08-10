<?php

namespace BookStack\App;

use BookStack\Activity\ActivityQueries;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\RecentlyViewed;
use BookStack\Entities\Queries\TopFavourites;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Entities\Repos\BookshelfRepo;
use BookStack\Entities\Tools\PageContent;
use BookStack\Http\Controller;
use BookStack\Uploads\FaviconHandler;
use BookStack\Util\SimpleListOptions;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
    * Display the homepage.
    */
    public function index(Request $request, ActivityQueries $activities)
    {
        $activity = $activities->latest(10);
        $draftPages = [];
        
        if ($this->isSignedIn()) {
            $draftPages = Page::visible()
            ->where('draft', '=', true)
            ->where('created_by', '=', user()->id)
            ->orderBy('updated_at', 'desc')
            ->with('book')
            ->take(6)
            ->get();
        }
        
        $recentFactor = count($draftPages) > 0 ? 0.5 : 1;
        $recents = $this->isSignedIn() ?
        (new RecentlyViewed())->run(12 * $recentFactor, 1)
        : Book::visible()->orderBy('created_at', 'desc')->take(12 * $recentFactor)->get();
        $favourites = (new TopFavourites())->run(6);
        $recentlyUpdatedPages = Page::visible()->with('book')
        ->where('draft', false)
        ->orderBy('updated_at', 'desc')
        ->take($favourites->count() > 0 ? 5 : 10)
        ->select(Page::$listAttributes)
        ->get();
        
        $homepageOptions = ['default', 'books', 'bookshelves', 'page'];
        $homepageOption = setting('app-homepage-type', 'default');
        if (!in_array($homepageOption, $homepageOptions)) {
            $homepageOption = 'default';
        }
        
        $commonData = [
            'activity'             => $activity,
            'recents'              => $recents,
            'recentlyUpdatedPages' => $recentlyUpdatedPages,
            'draftPages'           => $draftPages,
            'favourites'           => $favourites,
        ];
        
        // Add required list ordering & sorting for books & shelves views.
        if ($homepageOption === 'bookshelves' || $homepageOption === 'books') {
            $key = $homepageOption;
            $view = setting()->getForCurrentUser($key . '_view_type');
            $listOptions = SimpleListOptions::fromRequest($request, $key)->withSortOptions([
                'name' => trans('common.sort_name'),
                'created_at' => trans('common.sort_created_at'),
                'updated_at' => trans('common.sort_updated_at'),
            ]);
            
            $commonData = array_merge($commonData, [
                'view'        => $view,
                'listOptions' => $listOptions,
            ]);
        }
        
        if ($homepageOption === 'bookshelves') {
            $shelves = app(BookshelfRepo::class)->getAllPaginated(18, $commonData['listOptions']->getSort(), $commonData['listOptions']->getOrder());
            $data = array_merge($commonData, ['shelves' => $shelves]);
            
            return view('home.shelves', $data);
        }
        
        if ($homepageOption === 'books') {
            $books = app(BookRepo::class)->getAllPaginated(18, $commonData['listOptions']->getSort(), $commonData['listOptions']->getOrder());
            $data = array_merge($commonData, ['books' => $books]);
            
            return view('home.books', $data);
        }
        
        if ($homepageOption === 'page') {
            $homepageSetting = setting('app-homepage', '0:');
            $id = intval(explode(':', $homepageSetting)[0]);
            /** @var Page $customHomepage */
            $customHomepage = Page::query()->where('draft', '=', false)->findOrFail($id);
            $pageContent = new PageContent($customHomepage);
            $customHomepage->html = $pageContent->render(false);
            
            return view('home.specific-page', array_merge($commonData, ['customHomepage' => $customHomepage]));
        }
        
        return view('home.default', $commonData);
    }
    
    /**
    * Show the view for /robots.txt.
    */
    public function robots()
    {
        $sitePublic = setting('app-public', false);
        $allowRobots = config('app.allow_robots');
        
        if ($allowRobots === null) {
            $allowRobots = $sitePublic;
        }
        
        return response()
        ->view('misc.robots', ['allowRobots' => $allowRobots])
        ->header('Content-Type', 'text/plain');
    }
    
    /**
    * Show the route for 404 responses.
    */
    public function notFound()
    {
        return response()->view('errors.404', [], 404);
    }
    
    /**
    * Serve the application favicon.
    * Ensures a 'favicon.ico' file exists at the web root location (if writable) to be served
    * directly by the webserver in the future.
    */
    public function favicon(FaviconHandler $favicons)
    {
        $exists = $favicons->restoreOriginalIfNotExists();
        return response()->file($exists ? $favicons->getPath() : $favicons->getOriginalPath());
    }
    
    /**
    * Serve the application manifest.
    * Ensures a 'manifest.json'
    */
    public function manifest()
    {   
        $manifest = [
            "name" => config('app.name' | 'BookStack'), 
            "short_name" => "bookstack", 
            "start_url" => "/", 
            "scope" => "/", 
            "display" => "standalone", 
            "background_color" => "#fff", 
            "description" => config('app.name' | 'BookStack'), 
            "categories" => [
                "productivity", 
                "lifestyle" 
            ], 
            "launch_handler" => [
                "client_mode" => "focus-existing" 
            ], 
            "orientation" => "portrait", 
            "icons" => [
                [
                    "src" => "/icon-64.png", 
                    "sizes" => "64x64", 
                    "type" => "image/png" 
                ], 
                [
                    "src" => "/icon-32.png", 
                    "sizes" => "32x32", 
                    "type" => "image/png" 
                ], 
                [
                    "src" => "/icon-128.png", 
                    "sizes" => "128x128", 
                    "type" => "image/png" 
                ], 
                [
                    "src" => "icon-180.png", 
                    "sizes" => "180x180", 
                    "type" => "image/png" 
                ], 
                [
                    "src" => "icon.png", 
                    "sizes" => "256x256", 
                    "type" => "image/png" 
                ], 
                [
                    "src" => "icon.ico", 
                    "sizes" => "48x48", 
                    "type" => "image/vnd.microsoft.icon" 
                ], 
                [
                    "src" => "favicon.ico", 
                    "sizes" => "48x48", 
                    "type" => "image/vnd.microsoft.icon" 
                ],
            ],
        ]; 
        
        return response()->json($manifest);
    }
}
