<?php namespace App;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Collective\Html\HtmlFacade as Html;

class Activity extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'activity_log';

    /**
     * The attributes that should be casted to native types.
     * @var array
     */
    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the user that the activity belongs to.
     * @return object
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * Create an activity log entry.
     * @param  mixed    $data
     * @return boolean
     */
    public static function log($data = [])
    {
        if(!config('log.enabled'))
        {
            // logging not enabled.
            return;
        }

        if (is_object($data))
        {
            $data = (array) $data;
        }

        if (is_string($data))
        {
            $data = ['action' => $data];
        }

        $activity = new static;

        $user = \Auth::user();
        $activity->user_id = isset($user->id) ? $user->id : null;

        if (isset($data['userId']))
        {
            $activity->user_id = $data['userId'];
        }

        $activity->content_id   = isset($data['contentId'])   ? $data['contentId']   : null;
        $activity->content_type = isset($data['contentType']) ? $data['contentType'] : null;
        $activity->action       = isset($data['action'])      ? $data['action']      : null;
        $activity->description  = isset($data['description']) ? $data['description'] : null;
        $activity->details      = isset($data['details'])     ? $data['details']     : null;
        $activity->ip_address = Request::getClientIp();
        $activity->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'No UserAgent';

        $activity->save();

        return $activity;
    }

    /**
     * Get the name of the user.
     *
     * @return string
     */
    public function getName()
    {
        $user = $this->user;

        if (empty($user))
        {
            return "Unknown User";
        }

        return $user->name;
    }

    /**
     * Get a shortened version of the user agent with title text of the full user agent.
     *
     * @return string
     */
    public function getUserAgentPreview()
    {
        return substr($this->user_agent, 0, 42) . (strlen($this->user_agent) > 42 ? '<strong title="'.$this->user_agent.'">...</strong>' : '');
    }

    /**
     * Get the icon class name for the log entry's action.
     *
     * @return string
     */
    public function getIcon()
    {
        $actionIcons = config('log.action_icons');

        $actionFormatted = str_replace(' ', '_', trim(strtolower($this->action)));

        if (!is_null($this->action) && $this->action == "" || !isset($actionIcons[$actionFormatted]))
            return $actionIcons['x'];

        return $actionIcons[$actionFormatted];
    }

    /**
     * Get the markup for the log entry's icon.
     *
     * @return string
     */
    public function getIconMarkup()
    {
        return Html::icon($this->getIcon(), 0);
    }

    /**
     * Get the URL for the log entry's content type if possible.
     *
     * @return string
     */
    public function getUrl()
    {
        $contentTypeSettings = config('log.content_types.'.snake_case($this->content_type));

        if (!is_array($contentTypeSettings) || !isset($contentTypeSettings['uri']))
            return null;

        $uri = str_replace(':id', $this->content_id, $contentTypeSettings['uri']);
        $url = URL::to($uri);

        $baseUrl = str_replace('https://', '', str_replace('http://', '', config('app.url')));

        // remove subdomain if one exists
//        $url = preg_replace('/(http[s]?:\/\/)[A-Za-z0-9]*[\.]?('.str_replace('.', '\.', $baseUrl).')/', '${1}${2}', $url);

        // add subdomain if one is set
        if (isset($contentTypeSettings['subdomain']))
        {
            $subdomain = $contentTypeSettings['subdomain'];

            if (isset($subdomain) && $subdomain != "" && $subdomain !== false && !is_null($subdomain))
                $url = preg_replace('/(http[s]?:\/\/)('.str_replace('.', '\.', $baseUrl).')/', '${1}'.$subdomain.'.${2}', $url);
        }

        if (isset($contentTypeSettings['secure']) && $contentTypeSettings['secure'])
            $url = str_replace('http://', 'https://', $url);

        return $url;
    }

    /**
     * Get the linked description (if one is available). Otherwise, just get the description.
     *
     * @param  mixed    $class
     * @return string
     */
    public function getLinkedDescription($class = null)
    {
        if (is_null($this->getUrl()))
            return $this->description;

        return '<a href="'.$this->getUrl().'"'.(!is_null($class) ? ' class="'.$class.'"' : '').'>'.$this->description.'</a>' . "\n";
    }

    /**
     * Get the content item (if one is available).
     *
     * @param  boolean  $returnArray
     * @return object
     */
    public function getContentItem($returnArray = false)
    {
        $contentTypeSettings = config('log.content_types.'.snake_case($this->content_type));

        if (!is_array($contentTypeSettings) || !isset($contentTypeSettings['model']))
            return null;

        $model = $contentTypeSettings['model'];
        $item = $model::withTrashed()->find((int) $this->content_id);

        if ($returnArray && is_object($item) && method_exists($item, 'toArray'))
            return $item->toArray();

        return $item;
    }

}
