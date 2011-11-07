Pusher FuelPHP Class
==================

This is a FuelPHP Class to help working with Pusher API (http://pusherapp.com).
Inherited from Squeeks' PHP Pusher Library (https://github.com/squeeks/Pusher-PHP)
Usage:

    Pusher::init($key, $secret, $app_id);
    Pusher::trigger('my-channel', 'my_event', 'hello world');

Note: You need to set your API information in app/config/pusher.php

Arrays
------
Objects are automatically converted to JSON format:

    $array['name'] = 'joe';
    $array['message_count'] = 23;

    Pusher::trigger('my_channel', 'my_event', $array);

The output of this will be:

    "{'name': 'joe', 'message_count': 23}"

Socket id
---------
In order to avoid duplicates you can optionally specify the sender's socket id while triggering an event (http://pusherapp.com/docs/duplicates):

    Pusher::trigger('my-channel','event','data','socket_id');

Debugging
---------
You can either turn on debugging by setting the fifth argument to true, like so:

    Pusher::trigger('my-channel', 'event', 'data', null, true)

or with all requests:

    Pusher::init($key, $secret, $app_id, true);

On failed requests, this will return the server's response, instead of false.

JSON format
-----------

If your data is already encoded in JSON format, you can avoid a second encoding step by setting the sixth argument true, like so:

	Pusher::trigger('my-channel', 'event', 'data', null, false, true)

Private channels
----------------
To authorise your users to access private channels on Pusher, you can use the socket_auth function:

    Pusher::socket_auth('my-channel','socket_id');

Presence channels
-----------------
Using presence channels is similar to private channels, but you can specify extra data to identify that particular user:

    Pusher::presence_auth('my-channel','socket_id', 'user_id', 'user_info');

Presence example
----------------

First set this variable in your JS app:

    Pusher.channel_auth_endpoint = '/presence_auth/index';

Next, create the following in controller presence_auth.php:

    <?php
	class Controller_Presence_Auth extendes Controller_Rest {
	
		protected $rest_format = 'json';
		
		public function post_index()
		{
			// fetch a user record from database
			$id = Session::get('user_id');
			$user = Model_User::find($id);
			Pusher::init();
			$this->response->body(
				Pusher::presence_auth($_POST['channel_name'],
				$_POST['socket_id'],
				$user['id'],
				$user);
			);
		}
	}

Note: this assumes that you store your users in a model file called Model_User, and the current logged in user's id is stored on the session variables.
  

License
-------
Copyright 2011, Arkross. Not yet licensed, free to use.
Credit goes to Squeeks on his project: Pusher-PHP (https://github.com/squeeks/Pusher-PHP)

