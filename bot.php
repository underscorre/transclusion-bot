<?php

/**
 * TransclusionBot
 *
 * TransclusionBot is a simple MediaWiki bot that replaces transclusions of
 * templates with substitutions.
 *
 * This code is licensed under the **MIT License**. For more information please
 * see the "LICENSE" file that should have been distributed with this code.
 *
 * @author Underscorre
 * @copyright 2016 Underscorre
 * @license https://opensource.org/licenses/MIT The MIT License
 */

require_once 'vendor/autoload.php';

/**
 * TransclusionBot class.
 *
 * This is the only class that makes up TransclusionBot. Normally this would be
 * a bit better designed, but this is just meant to be a cheap and cheerful
 * script.
 */
class TransclusionBot {

    /**
     * @var League\CLImate\CLImate $cli Instance of CLImate to easily output to
     * the console, read arguments, etc.
     */
    private $cli;

    /**
     * @var Wikimate $wikimate Instance of Wikimate to easily make calls to the
     * MediaWiki API.
     */
    private $wikimate;

    /**
     * @var string $wiki URL of the api.php on the wiki we're editing on.
     */
    private $wiki;

    /**
     * @var string $template Name of the template we want to change to
     * substitution.
     */
    private $template;

    /**
     * @var boolean $forceBot Should we only edit if we have bot rights?
     */
    private $forceBot;

    /**
     * @var string $username Username of the account we will edit via. If set to
     * an empty string, we try to edit anonymously.
     */
    private $username;

    /**
     * @var string|null $password Password of the account we will edit via. Set
     * to null if we're editing anonymously.
     */
    private $password;

    /**
     * @var boolean $anon True if we're editing anonymously.
     */
    private $anon;

    /**
     * TransclusionBot class constructor.
     *
     * Sets up the necessary objects, outputs the name of the program, checks
     * the arguments passed to the program.
     *
     * @return TransclusionBot A new instance of the TransclusionBot class.
     */
    public function __construct()
    {
        $this->cli = new League\CLImate\CLImate();
        $this->cli->clear();
        $this->displayWelcome();
        $this->createArguments();
        $this->wikimate = new Wikimate($this->wiki);
    }

    /**
     * Displays the program's welcome message.
     *
     * Displays a "splash screen" for the program, containing its name + the
     * author's name.
     */
    protected function displayWelcome()
    {
        $this->cli->out('<light_green><bold>TransclusionBot</bold>' .
                        '</light_green> by ' .
                        '<light_green>Underscorre</light_green>');
        $this->cli->border();
    }

    /**
     * Creates + checks the argument structure for the program.
     *
     * Uses CLImate to specify the valid arguments to the program, then checks
     * those against the arguments actually passed to the program. If the
     * arguments given are not valid, we exit.
     */
    protected function createArguments()
    {
        $this->cli->arguments->add([
            'wiki'     => [
                'prefix'      => 'w',
                'longPrefix'  => 'wiki',
                'description' => 'Path to the api.php of the wiki the bot ' .
                                 'should edit on',
                'required'    => true
            ],
            'template' => [
                'prefix'      => 't',
                'longPrefix'  => 'template',
                'description' => 'Template that should have its ' .
                                 'transclusions replaced with substitutions',
                'required'    => true
            ],
            'forcebot' => [
                'prefix'      => 'b',
                'longPrefix'  => 'forcebot',
                'description' => 'If set, edits will only be made if the ' .
                                 'account is a bot',
                'noValue'     => true
            ]
        ]);

        try {
            $this->cli->arguments->parse();
        } catch (Exception $e) {
            $this->cli->usage();
            $this->error('Incorrect usage');
        }

        $this->wiki = $this->cli->arguments->get('wiki');
        $this->template = $this->cli->arguments->get('template');
        $this->forceBot = $this->cli->arguments->get('forcebot') ? true : false;
    }

    /**
     * Runs the bot.
     */
    public function run()
    {
        $this->username = $this->getUsername();
        if ($this->username !== '') {
            $this->password = $this->getPassword();
        } else {
            $this->anon = true;
        }

        if (!$this->anon) {
            $this->login();
        }

        $pages = $this->getTransclusions();
        $this->edit($pages);
    }

    /**
     * Gets the username.
     *
     * Gets the username from the user with which we should make edits.
     *
     * @return string The username given by the user.
     */
    protected function getUsername()
    {
        $input = $this->cli->input('Please enter the username to use:');
        return $input->prompt();
    }

    /**
     * Gets the password.
     *
     * Gets the password from the user for the account with which we will make
     * edits.
     *
     * @return string The password given by the user.
     */
    protected function getPassword()
    {
        $input = $this->cli->password('Please enter your password:');
        return $input->prompt();
    }

    /**
     * Tries to login.
     *
     * Attempts to login to the given wiki with the given username + password.
     * Exits if the login fails.
     */
    protected function login()
    {
        $this->cli->inline('Attempting login... ');
        if ($this->wikimate->login($this->username, $this->password)) {
            $this->cli->green()->out("Login successful :)");
        } else {
            $this->cli->to('error')->error('Login failed :(');
            $this->error($this->wikimate->getError());
        }

        if ($this->forceBot) {
            if (!$this->checkBot()) {
                $this->error('Account does not have bot rights');
            } else {
                $this->cli->green()->out("Account has bot rights");
            }
        }
    }

    /**
     * Checks if the logged in account has bot rights.
     *
     * @return boolean True if the account has bot rights, false otherwise.
     */
    public function checkBot()
    {
        $this->cli->inline('Checking for bot rights... ');
        $data = [
            'meta'   => 'userinfo',
            'uiprop' => 'groups'
        ];
        $response = $this->wikimate->query($data);
        return in_array('bot', $response['query']['userinfo']['groups']);
    }

    /**
     * Gets pages where a certain template is transcluded.
     *
     * @todo Luckily on the wiki this was developed for, everything we needed to
     * use this script on was transcluded <500 times, however, for wikis with
     * more transclusions than that, this script will only get the first 500
     * pages. Need to build in query-continue functionality.
     *
     * @return mixed[] Array of pages where template is transcluded.
     */
    public function getTransclusions()
    {
        $this->cli->inline('Getting transclusions... ');
        $data = [
            'generator' => 'embeddedin',
            'geititle'  => sprintf('Template:%s', $this->template),
            'geilimit'  => 500,
            'prop'      => 'info|revisions',
            'intoken'   => 'edit',
            'rvprop'    => 'content'
        ];
        $pages = $this->wikimate->query($data);
        if (count($pages) > 0) {
            $this->cli->green()->out("Got pages!");
            return $pages['query']['pages'];
        } else {
            $this->error('Failed to get any pages. Is the template '.
                         'transcluded anywhere?');
        }
    }

    /**
     * Edits an array of pages.
     *
     * Changes the transclusion to a substitution on the given pages.
     *
     * @todo Currently we don't account for any parameters passed to the
     * template, since the wiki this script was created for did not have
     * parameters for the templates that needed cleaning. Support parameters :)
     *
     * @param mixed[] Pages to edit.
     */
    public function edit($pages)
    {
        $this->cli->boldBlue()->out("Now editing pages...");
        $this->cli->out("Any errors will appear below.");
        $i = 0;
        $progress = $this->cli->progress()->total(count($pages));
        foreach ($pages as $id => $page) {
            $i += 1;
            usleep(1500000); // Sleep for 1.5 secs to prevent making rq's too
                             // quickly
            $text = $page['revisions'][0]['*'];
            $text = preg_replace('/\{\{\s*' . preg_quote($this->template, '/') .
                '\s*(?=\}\}|\|)/', '{{subst:' . $this->template, $text);
            $data = [
                'title'          => $page['title'],
                'text'           => $text,
                'md5'            => md5($text),
                'bot'            => true,
                'token'          => $page['edittoken'],
                'starttimestamp' => $page['starttimestamp'],
                'minor'          => true,
                'summary'        => 'Replacing some transclusions with ' .
                                    'substitutions'
            ];
            $result = $this->wikimate->edit($data);
            if (!array_key_exists('edit', $result) ||
                !array_key_exists('result', $result['edit']) ||
                $result['edit']['result'] !== 'Success') {
                $this->cli->to('error')->error('Failed editing ' .
                                               $page['title'] . ' - ' .
                                               $result['edit']['result']);
            }
            $progress->current($i);
        }
    }

    /**
     * Outputs an error & exits.
     *
     * Outputs an error to STDERR and exits with exit code "1" to indicate
     * error.
     */
    public function error($message)
    {
        $this->cli->to('error')->error($message);
        exit(1);
    }

}

$bot = new TransclusionBot();
$bot->run();
