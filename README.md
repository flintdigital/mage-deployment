# Magento Deployment Script

## Overview
This simple script generates a Magento Installation script that allows a less technical user to create a new working Magento instance with just a few clicks.

- Works with Mac Running LAMP & Linux. Additional work may be required when running MAMP. 
- User form generates shell script that can be run from your local host. 
- Allows user to select plugins and themes that have been added to the config.json (see instructions below.)
- As the core and each module have been checked out from their respective repo, they are commited to the "client" repo. 
- Runs the Magento Install Script from command line with user provided form inputs. 
- Configures virtual host files. 


### Usage 

Getting the script setup will require some effort and knowledge of how this stuff all works. 
#### Configuring the json file

You will likely want to add modules (and themes) in the initial build process. TO do so you will need to create a config.json file. We have added a sample of one with the script. 

##### To add a Module or Theme
To add a Module add something like this inside the plugin object.

`
 "mage-onestep-checkout-iwd": {
            "name": "Magento One Step Checkout - IWD",
            "repo_url": "git@github.com:flintdigital/mage-onestep-checkout-iwd.git",
            "privacy": "public"
        },
`

1. The key (mage-onestep-checkout-iwd) should match the repo slug. 
2. name is used by the form generation script
3. repo_url is Repo URL of the repo you are checking the code out of. 
4. privacy can be left as public. If you are accessing a private repo you will need to make sure oyu have the public key installed.
5. The same follows for themes except you will need to place the information inside the "theme" object. 



#### General
1. Download the script. 
2. Launch the script in your web browser (e.g. `http://localhost/mage-deployment-script.php`)
3. Fill out the form. 
4. Download the script and save to the directory that you will be running Magento in. 
5. Open up terminal and `cd` into the directory you downloaded the script into. 
6. Run `sh [SCRIPTNAME].sh` where [SCRIPTNAME] is the actual scriptname. 

##### Notes
- Make sure to run command as super user: 'sudo sh [client]_init.sh' where client is the domain. 


#Todo
- test more thouroughly on mac MAMP environment. 
- See issue tracker
