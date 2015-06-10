<?php
/**
 * 
 * Mage Deployment Script
 * 
TO DO
 * mac vhost
 * create database
 * private repos *
 * git push
 * local.xml ? 
 * include utils in config

Script currnetly does this:
 * Displays a web form to input the necessary data
 * On Submit, it creates a shell script and sends it to download
 * The shell script does this:
    * Clones the empty customer repository
    * Initializes modgit
    * Gets Magento Latest
    * Commits Magento Initial
    * Runs Magento CLI Install
    * Gets Each plugin and theme
    * Commit each of those, one by one
    * Configures the vhost for linux


If on linux, make sure you have default.local vhost at /var/www


 * 
 *  */

error_reporting(E_ALL);ini_set('display_errors', 1);

define("DEFAULT_APACHEDIR_MAC", '/Users/flintdigital/Sites/');
define("DEFAULT_APACHEDIR_LINUX", '/var/www/');
define("HTTPD_CONF_FILE_MAC", "/Volumes/Macintosh HD/Applications/MAMP/conf/apache/httpd.conf");

$config = file_get_contents(dirname(__FILE__).'/config.json');
$config = json_decode($config, true);

$errors = [];
    
if(is_array($_POST) && !empty($_POST)){
    //TODO: Check required fields: mainly looking for a theme. Is it required?
    
    //This var will have all the script contents, in the end this will be added to a mage-deployment.sh file
    $script = "";
    
    $customerGitRepo = trim($_POST['git_repo']);
    $useGit = !empty($customerGitRepo);
    
    //Get the client name: domain withouth local. and .com
    $client = $_POST['args']['url'];
    $domain = str_replace('local.', '', $client);
    $domain = trim($domain);
    $domain = str_replace('/', '', $domain);
    $client = explode('.', $domain);
    $client = $client[0];
    $user = $_POST['environment_user'];
    
    //Get Base Dir, create code/ folder
    addCmdToScript('FILE=$(readlink -f $0) && DIR=$(dirname $FILE)', 'Get initial directory');
    addCmdToScript('cd $DIR');
    addCmdToScript('mkdir code', 'Add code/ folder to current directory');
    
    //Clone the Customer empty Repository if git repo is set: we use su - USER because this script should be run as root (using sudo)
    addCmdToScript('git clone '.$customerGitRepo.' $DIR/code', "Clone the customer git repository", '', true, $user);
    
    addCmdToScript('PROJECT_DIR=$(find $DIR -mindepth 1 -maxdepth 1 -type d)', 'Get Project main directory');
    addCmdToScript('echo "Project base directory $PROJECT_DIR"');
    
    
    //Initialize modgit
    addCmdToScript('modgit init', "Initialize modgit", '' , false , $user);
    
    //modgit Install the base Magento
    addCmdToScript('modgit add '.$config['platform']['slug'].' '.$config['platform']['repo_url'], "Add the base Magento Code", '' , false , $user);
    
    //Add all the code to git if git repo is set
    addCmdToScript("git add -A", "Add all the Magento Files to the Git Repository", '', true, $user);
 
    
    //Commit the files to git repo if it is set
    addCmdToScript("git commit -a -m 'Deployment Script: Adding Magento base installation'", "Commit the files added", '', true, $user);
    
    
//    addCmdToScript('exit');
    
    
    //Create Magento Installer command
    $magentoInstallerCmd = createMageInstallCmd($_POST['args'], $config['mage_config_defaults']);
    
    //Run Magento Installer CLI
    addCmdToScript($magentoInstallerCmd, "Run Magento Installer CLI", 'Running Magento Installer', false, $user);
    
    //TODO: Add local.xml to git, ?? run --assume-unchanged on it??
    
    
    
    //Go through each module selected, modgit add, git add, git commit, git push
    $requestedPlugins = array_key_exists('plugins', $_POST) ? $_POST['plugins'] : [];
    if(is_array($requestedPlugins)){
        foreach($requestedPlugins as $requestedPlugin) {
            if(!array_key_exists($requestedPlugin, $config['plugins'])) {
                $errors[] = "The plugin $requestedPlugin doesn't seem to exist in the configuration file. Add it to the config.json file or ask somebody to do it for you.";
                continue;
            }
            
            $pluginData = $config['plugins'][$requestedPlugin];
            
            //Modgit install the module
            addCmdToScript('modgit add '.$requestedPlugin.' '.$pluginData['repo_url'], "Modgit Install the {$pluginData['name']} module", '' , false , $user);
            
            //Stage the files to the git repo, if it exists
            addCmdToScript("git add -A", "Add all the Module Files to the Git Repository", '', true, $user);
            
            //Commit the files to the git repo, if it exists
            addCmdToScript("git commit -a -m 'Deployment Script: Adding Module {$pluginData['name']} ($requestedPlugin).'", "Commit the module files added", '', true, $user);
        }
    }
    
    
    if(array_key_exists('theme', $_POST)) {
        if(!array_key_exists($_POST['theme'], $config['themes'])) {
            $errors[] = "The theme {$_POST['theme']} doesn't seem to exist in the configuration file. Add it to the config.json file or ask somebody to do it for you.";
        }
        
        else {
            //modgit Install the Theme requested
            addCmdToScript('modgit add '.$_POST['theme'].' '.$config['themes'][$_POST['theme']]['repo_url'], "Modgit Install the {$config['themes'][$_POST['theme']]['name']} Theme", '' , false , $user);

            //Add all the code to git if git repo is set
            addCmdToScript("git add -A", "Add all the Magento Files to the Git Repository", '', true, $user);

            //Commit the files to git repo if it is set
            addCmdToScript("git commit -a -m 'Deployment Script: Adding Theme {$config['themes'][$_POST['theme']]['name']} ({$_POST['theme']})'", "Commit the Theme files added", '', true, $user);
        }
    }
    
    //Let's create the vhost now
    if($_POST['environment'] == 'mac') {
        $httpdConfFile = trim(HTTPD_CONF_FILE_MAC);
        $httpdConfFile = str_replace(' ', '\ ', $httpdConfFile);
        //$vhostString = "\n\n#vhost configuration for $domain\n<VirtualHost *>\nDocumentRoot ".'$PROJECT_DIR'."\nServerName {$_POST['args']['url']}\n</VirtualHost>";
        addCmdToScript("echo \"#vhost configuration for {$_POST['args']['url']}\" >> $httpdConfFile", "Add the vhost configuration");
        addCmdToScript("echo \"<VirtualHost *>\" >> $httpdConfFile");
        addCmdToScript("echo \"DocumentRoot ".'$PROJECT_DIR'."\" >> $httpdConfFile");
        addCmdToScript("echo \"ServerName {$_POST['args']['url']}\" >> $httpdConfFile");
        addCmdToScript("echo \"</VirtualHost>\" >> $httpdConfFile");
        
        //Add host to /etc/hosts
        addCmdToScript("echo \"127.0.0.1         local.$domain\" | tee -a /etc/hosts", 'Add host to /etc/hosts');
    }
    
    else if($_POST['environment'] == 'linux') {
        //Check to see if Link exists, Delete if it does.
        addCmdToScript("if [ -d \"".DEFAULT_APACHEDIR_LINUX."$client\" ]; then\n\trm ".DEFAULT_APACHEDIR_LINUX."$client\nfi\n", 'Check to see if Link exists, Delete if it does');
        
        //Create Link to Magento's Root in /var/www
        addCmdToScript('ln -s $PROJECT_DIR/ "'.DEFAULT_APACHEDIR_LINUX.'/var/www/'.$client.'"', "Create Link to Magento's Root in /var/www");
        addCmdToScript("chown -h {$_POST['environment_user']}:{$_POST['environment_user']} \"".DEFAULT_APACHEDIR_LINUX."$client\"");
        
        //Copy paste the vhost template file
        addCmdToScript("cp /etc/apache2/sites-available/default.local /etc/apache2/sites-available/$domain.conf", 'Copy paste the vhost template file');
        
        //Configure vhost config file to use the project name
        addCmdToScript("sed -i \"s/template/$client/g\" /etc/apache2/sites-available/$domain.conf", 'Configure vhost config file to use the project name');

        //Enable the site in apache
        addCmdToScript("a2ensite $domain.conf", 'Enable the site in apache');
        
        //Reload Apache Config
        addCmdToScript("/etc/init.d/apache2 reload", 'Reload Apache Config');
        
        //Add host to /etc/hosts
        addCmdToScript("echo \"127.0.0.1         local.$domain\" | tee -a /etc/hosts", 'Add host to /etc/hosts');
        
        addCmdToScript("chown -hR {$_POST['environment_user']}:{$_POST['environment_user']}".' $PROJECT_DIR');
    }
    
    //TODO Git Push
    
    
    
    if(empty($errors)) {
        header("Content-type: text/plain");
        header("Content-Disposition: attachment; filename={$client}_init.sh");
        print $script;
        exit;
    }

    
}//if $_POST

?>

<html>
    <head>
        <title>Flint Digital - Magento Initial Deployment Script</title>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet">
    </head>
    
    <body>
        <div class="container">
            <?php if(!empty($errors)):?>
            <?php foreach($errors as $error): ?>
            <div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                <span class="sr-only">Error:</span>
                <?= $error ?>
            </div>
            <?php endforeach ?>
            <?php endif ?>
            
            <h3>Description</h3>
            <p>Deploy an initial Magento Installation with this simple form. </p>
            
            <h3>Instructions</h3>
            <ul>
                <li>Fill the data requested</li>
                <li>Submit the form to download the script</li>
                <li>Move the script from your downloads folder into the folder you'll want the code to be in</li>
                <li>Run the script as root: sudo sh abcd_init.sh</li>
            </ul>
            
            <h3>Requirements</h3>
            <ul>
                <li>Client empty Repository must already be created (usually in Assembla)</li>
                <li>If you don't set a Repository, the code won't be tracked with git. Use this for TESTING PURPOSES ONLY.</li>
                <li>Git must be installed on your local environment</li>
                <li>DB MUST exist</li>
            </ul>
            
            <br /><br /><hr /><br />
            
            
            <form method="post">
                <h3>1) Input this data about your environment </h3>
                <br />
                <div class="row">
                    <div class="col-md-6">
                        <label for="environment">Which is your OS?</label>
                        <select id="environment" name="environment" required class="form-control" >
                            <option value="">Select One</option>
                            <option value="mac">Mac OS</option>
                            <option value="linux">Linux</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="environment_user">OS username</label>
                        <input required type="text" class="form-control" id="environment_user" name="environment_user" value="<?php echo empty($_POST['environment_user']) ? '' : $_POST['environment_user']?>" />
                    </div>
                </div>
                
                
                <br /><hr />
                <h3>2) Fill the following data</h3>
                <br />
                
                <h4>2.1) Git / Github Data</h4>
                <br />
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="git_repo">Customer Git Repository (usually Assembla)</label>
                        <input type="text" class="form-control" id="git_repo" name="git_repo" value="<?php echo empty($_POST['git_repo']) ? '' : $_POST['git_repo']?>" />
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="github_user">Github User (only needed if private plugins are selected)</label>
                        <input type="text" class="form-control" id="github_user" name="github_user" value="<?php echo empty($_POST['github_user']) ? '' : $_POST['github_user']?>" />
                    </div>
                    
                    <div class="col-md-6">
                        <label for="github_password">Github Password (only needed if private plugins are selected)</label>
                        <input type="password" class="form-control" id="github_password" name="github_password" value="<?php echo empty($_POST['github_password']) ? '' : $_POST['github_password']?>" />
                    </div>
                </div>
                <br />
                <h4>2.2) Database Credentials</h4>
                <br />
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="db_user">DB User</label>
                        <input required type="text" class="form-control" id="db_user" name="args[db_user]" value="<?php echo empty($_POST['args[db_user]']) ? '' : $_POST['args[db_user]']?>" />
                    </div>
                    
                    <div class="col-md-6">
                        <label for="db_pass">DB Password</label>
                        <input required type="text" class="form-control" id="db_password" name="args[db_pass]" value="<?php echo empty($_POST['args[db_pass]']) ? '' : $_POST['args[db_pass]']?>" />
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="db_name">DB Name</label>
                        <input required type="text" class="form-control" id="db_name" name="args[db_name]" value="<?php echo empty($_POST['args[db_name]']) ? '' : $_POST['args[db_name]']?>" />
                    </div>
                </div>
                
                <br />
                <h4>2.3) Magento Data</h4>
                <br />
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="timezone">Timezone</label>
                        <select required class="form-control" id="timezone" name="args[timezone]">
                            <option value="">Please Select One</option>
                            <?php foreach($config["timezones"] as $timezone): ?>
                            <option value="<?= $timezone['value']?>"><?= $timezone['name'] ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="url">Base URL</label>
                        <input required type="text" class="form-control" id="url" name="args[url]" value="<?php echo empty($_POST['args[url]']) ? '' : $_POST['args[url]']?>" placeholder="local.clientdomain.com" />
                    </div>
                </div>
                
                <br />
                <h4>2.4) Magento Admin Data</h4>
                <br />
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="admin_firstname">Admin Firstname</label>
                        <input required type="text" class="form-control" id="admin_firstname" name="args[admin_firstname]" value="<?php echo empty($_POST['args[admin_firstname]']) ? '' : $_POST['args[admin_firstname]']?>" />
                    </div>
                    
                    <div class="col-md-6">
                        <label for="admin_lastname">Admin Lastname</label>
                        <input required type="text" class="form-control" id="admin_lastname" name="args[admin_lastname]" value="<?php echo empty($_POST['args[admin_lastname]']) ? '' : $_POST['args[admin_lastname]']?>" />
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="admin_email">Admin Email</label>
                        <input required type="text" class="form-control" id="admin_email" name="args[admin_email]" value="<?php echo empty($_POST['args[admin_email]']) ? '' : $_POST['args[admin_email]']?>" />
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="admin_username">Admin Username</label>
                        <input required type="text" class="form-control" id="admin_username" name="args[admin_username]" value="<?php echo empty($_POST['args[admin_username]']) ? '' : $_POST['args[admin_username]']?>" />
                    </div>
                    
                    <div class="col-md-6">
                        <label for="admin_lastname">Admin Password</label>
                        <input required type="text" class="form-control" id="admin_password" name="args[admin_password]" value="<?php echo empty($_POST['args[admin_password]']) ? '' : $_POST['args[admin_password]']?>" />
                    </div>
                </div>
                
                <br /><hr />
                <h3>3) Select the Plugins to Install</h3>
                <p>NOTE: Private Plugins not implemented yet</p>
                <br />
                <?php foreach($config['plugins'] as $slug=>$repoData):?>
                <input type="checkbox" id="<?= $slug ?>" value="<?= $slug ?>" name="plugins[]" />
                <label for="<?= $slug ?>"><?=$repoData['name']?></label>
                <?php if($repoData['privacy'] == 'private'): ?>
                (private)
                <?php endif ?>
                <br />
                <?php endforeach;?>
                
                <br /><hr />
                <h3>4) Select the Theme to Install</h3>
                <?php foreach($config['themes'] as $slug=>$repoData):?>
                <input type="radio" id="<?= $slug ?>" value="<?= $slug ?>" name="theme" />
                <label for="<?= $slug ?>"><?=$repoData['name']?></label>
                <?php if($repoData['privacy'] == 'private'): ?>
                (private)
                <?php endif ?>
                <br />
                <?php endforeach;?>
                
                
                <br /><hr />
                <h3>5) All Set? Download the script then! </h3>
                <input type="submit" value="Download Script" class="btn btn-primary"/>
            </form>
        
        
        
        
        <p>This script will:</p>
        <ul>
            <li>Select Platform</li>
            <li>Clone empty repo</li>
            <li>Init modgit</li>
            <li>Add Mage latest</li>
            <li>Commit and push</li>
            <li>Run mage Install</li>
            <li>Commit and push</li>
            <li>Select plugins/theme/utils</li>
            <li>Modgit add each selected. Commit and push each selected</li>
        </ul>
        
        
        
        </div>
        
        
        <!--<pre><?php var_dump($config)?></pre>-->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    </body>
</html>


<?php 
//Helper functions

//Appends a command to the global $script variable
//Helps to wrap up the useGit condition and the new line after each command
function addCmdToScript($command, $comment='', $echoComment='', $isGit=false, $user=false) {
    global $script, $useGit;

    if(!$isGit || ($isGit && $useGit)) {
        $comment = trim($comment);

        if($comment) {
            $script .= "#$comment\n";
        }

        if($echoComment)
            $script .= 'echo '.$echoComment."\n";
        
        if($user) {
            $command = 'su - '.$user.' -c "cd $PROJECT_DIR && '.$command.'"';
        }
        
        $script .= "$command\n\n";
    }
}


function createMageInstallCmd($post, $defaults) {
    $command = 'php -f install.php --';
    
    $args = array_merge($post, $defaults);
    ksort($args);
    
    foreach($args as $key=>$value) {
        $command .= ' --'.$key;
        
        if(!empty($value))
            $command .= " '".$value."'";
    }
    
    return $command;
}

?>