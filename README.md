# Magento Deployment Script

## Overview
This simple admin panel allows a non technical user to create a new working Magento instance with just a few clicks.

- Works with Mac (running MAMP) & Linux
- User form generates shell script that can be run from your local host. 
- Allows user to select plugins and themes that have been added to the config.json (see instructions below.)
- As the core and each module have been checked out from their respective repo, they are commited to the client repo. 
- Runs the Magento Install Script from command line with user provided form inputs. 


### Usage 

- Make sure to run command as super user: 'sudo sh [client]_init.sh' where client is the domain. 


#Todo
- test more thouroughly on mac MAMP environment. 
- See issue tracker