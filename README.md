# Mautic Automatic Renewal of Gmail Access Token

This plugin connects as needed with the Gmail Api in your Google Cloud account to set or renew your Gmail access token in the Mautic mailer_dsn.

* Clone the repository in the `plugins` folder.
* Remove the active cache folder (prod, dev, etc.) in `var/cache`.
* Go to <img src="Assets/images/settings.png" width="12" height="12"> -> plugins
* Click once or twice on the install button.
* Click on the `Gmail Smtp` icon.
* Activate the plugin.
* Enter the Gmail Client ID and Gmail Client Secret that you have set in your Google Cloud Account.
* Authorize the app (the plugin) with your Gmail account.