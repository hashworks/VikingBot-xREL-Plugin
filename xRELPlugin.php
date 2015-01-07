<?php

class xRELPlugin extends basePlugin {

	private $disabled;

	/**
	 * Called when plugins are loaded
	 *
	 * @param mixed[]	$config
	 * @param resource 	$socket
	**/
	public function __construct($config, $socket) {
		parent::__construct($config, $socket);
		$this->disabled = false;
		if (!ini_get('allow_url_fopen')) {
			try {
				ini_set('allow_url_fopen', '1');
			} catch (Exception $e) {
				logMsg("Unable to enable allow_url_fopen, disabling youtubePlugin.");
				$this->disabled = true;
			}
		}
	}

	/**
	 * @return array[]
	 */
	public function help() {
		if ($this->disabled === true) {
			return array();
		}
		return array(
			array(
				'command'     => 'upcoming',
				'description' => 'Responds with a list of upcoming movies.'
			),
			array(
				'command'     => 'latest [[hd-]movie|[hd-]tv|game|update|console|[hd-]xxx]',
				'description' => 'Responds with a list of latest releases.'
			),
			array(
				'command'     => 'hot [movie|tv|game|console]',
				'description' => 'Responds with a list of latest hot releases.'
			),
			array(
				'command'     => 'nfo <dirname>',
				'description' => 'Responds with a nfo link for a scene dirname.'
			)
		);
	}

	/**
	 * Called when messages are posted on the channel
	 * the bot are in, or when somebody talks to it
	 *
	 * @param string $from
	 * @param string $channel
	 * @param string $msg
	 */
	public function onMessage($from, $channel, $msg) {
		if ($this->disabled === true) {
			return;
		}
		$strings  = array();
		$upcoming = $this->getCommandQuery($msg, "upcoming");
		$latest   = $this->getCommandQuery($msg, "latest");
		$hot      = $this->getCommandQuery($msg, "hot");
		$nfo      = $this->getCommandQuery($msg, "nfo");
		if ($upcoming !== false) {
			$strings = $this->getUpcoming();
		} elseif ($latest !== false) {
			$strings = $this->getLatest($latest);
		} elseif ($hot !== false) {
			$strings = $this->getHot($hot);
		} elseif ($nfo !== false) {
			$strings = array($this->getNfo($nfo));
		// } else {
		// 		// Check for dirnames in last post.
		// 	preg_match_all("/[a-z0-9._]{4,}-[a-z0-9]{3,}/i", $msg, $matches);
		// 	$matches = array_slice($matches[0], 0, 5);
		// 	foreach ($matches as $dirname) {
		// 		$nfo = $this->getNfo($dirname, false);
		// 		if ($nfo !== false) {
		// 			$strings[] = $nfo;
		// 		}
		// 	}
		}
		if (count($strings) == 1) {
			$this->sendMessage($channel, $strings[0], $from);
		} else {
			foreach($strings as $string) {
				$this->sendMessage($channel, $string);
			}
		}
	}

	private function stringifyReleaseData($data, $size = true, $url = true) {
		$string = array();
		if (isset($data['dirname'])) {
			$string[] = $data['dirname'];
		} else {
			return false;
		}
		if ($size && isset($data['size']['number'])) {
			$string[] = "[" . $data['size']['number'] . $data['size']['unit'] . "]";
		}
		if ($url && isset($data['link_href'])) {
			$string[] = "- " . $this->getShortUrl($data['link_href']);
		}
		return join(' ', $string);
	}

	/**
	 * @return string[]
	 */
	private function getUpcoming() {
		$data = @file_get_contents('http://api.xrel.to/api/calendar/upcoming.json');
			// remove /*-secure- ... */ encapsulation
		$data = trim(substr($data, 10, count($data)-3));
		if (!empty($data) && ($data = json_decode($data, true)) !== NULL) {
			if (isset($data["payload"])) {
				$data   = array_slice($data["payload"], 0, 5);
				$return = array();
				foreach ($data as $upcoming) {
					if (isset($upcoming['title'])) {
						if (isset($upcoming['genre']) && !empty($upcoming['genre'])) {
							$return[] = $upcoming['title'] . " [" . $upcoming['genre'] . "] " . "[" . $this->getShortUrl($upcoming['link_href']) . "]";
						} else {
							$return[] = $upcoming['title'] . $this->getShortUrl($upcoming['link_href']) . "]";
						}
					} else {
						LogMsg(var_dump($upcoming));
					}
				}
				if (count($return) > 0) {
					return $return;
				}
			}
		}
		return array('Failed to fetch upcoming stuff from xREL.to...');
	}

	/**
	 * @param string $from
	 * @param string $channel
	 * @param string $type
	 *
	 * @return string[]
	 */
	private function getLatest($type = '') {
		$type = trim($type);
		if (empty($type)) {
			$data = @file_get_contents('http://api.xrel.to/api/release/latest.json?per_page=5&filter=6');
		} elseif($type == 'movie' || $type == 'tv' || $type == 'game' || $type == 'update' || $type == 'console' || $type == 'xxx' || $type == 'hd-movie' || $type == 'hd-tv' || $type == 'hd-xxx') {
			switch($type) {
				case 'movie':
					$category_name = 'movies';
					break;
				case 'game':
					$category_name = 'games';
					break;
				case 'update':
					$type          = 'game';
					$category_name = 'update';
					break;
				case 'hd-movie':
					$type          = 'movie';
					$category_name = 'hdtv';
					break;
				case 'hd-tv':
					$type          = 'tv';
					$category_name = 'hdtv';
					break;
				case 'hd-xxx':
					$type          = 'xxx';
					$category_name = 'hdtv';
					break;
				default:
					$category_name = $type;
			}
			$data = @file_get_contents('http://api.xrel.to/api/release/browse_category.json?category_name=' . $category_name .
										'&ext_info_type=' . $type);
		} else {
			return array('Unknown type. For a list of types enter #help latest');
		}
			// remove /*-secure- ... */ encapsulation
		$data = trim(substr($data, 10, count($data)-3));
		if (!empty($data) && ($data = json_decode($data, true)) !== NULL) {
			if (isset($data['payload']['list'])) {
				$data   = $data['payload']['list'];
				$return = array();
				$data   = array_slice($data, 0, 5);
				foreach ($data as $release) {
					$string = $this->stringifyReleaseData($release, true, true);
					if ($string !== false) {
						$return[] = $string;
					}
				}
				if (count($return) > 0) {
					return $return;
				}
			}
		}
		return array('Failed to fetch latest releases from xREL.to...');
	}

	/**
	 * @param string $from
	 * @param string $channel
	 * @param string $type
	 *
	 * @return string[]
	 */
	private function getHot($type = '') {
		$type = trim($type);
		if (empty($type)) {
			$data = @file_get_contents('http://api.xrel.to/api/release/browse_category.json?category_name=hotstuff');
		} elseif ($type == 'movie') {
			$data = @file_get_contents('http://api.xrel.to/api/release/browse_category.json?category_name=topmovie&ext_info_type=movie');
		} elseif ($type == 'tv' || $type == 'game' || $type == 'console') {
			$data = @file_get_contents('http://api.xrel.to/api/release/browse_category.json?category_name=hotstuff&ext_info_type=' . $type);
		} else {
			return array('Unknown type. For a list of types enter #help hot');
		}
			// remove /*-secure- ... */ encapsulation
		$data = trim(substr($data, 10, count($data)-3));
		if (!empty($data) && ($data = json_decode($data, true)) !== NULL) {
			if (isset($data['payload']['list'])) {
				$data = $data['payload']['list'];
				$return = array();
				$data   = array_slice($data, 0, 5);
				foreach ($data as $release) {
					$string = $this->stringifyReleaseData($release, true, true);
					if ($string !== false) {
						$return[] = $string;
					}
				}
				if (count($return) > 0) {
					return $return;
				}
			}
		}
		return array('Failed to fetch latest hot releases from xREL.to...');
	}

	private function getNfo($dirname, $showError = true) {
		if (!empty($dirname)) {
			$data = @file_get_contents('http://api.xrel.to/api/release/info.json?dirname=' . $dirname);
			// remove /*-secure- ... */ encapsulation
			$data = trim(substr($data, 10, count($data)-3));
			if (!empty($data) && ($data = json_decode($data, true)) !== NULL) {
				if (isset($data['payload'])) {
					$release = $data['payload'];
					$string = $this->stringifyReleaseData($release, true, true);
					if ($string !== false) {
						return $string;
					}
				}
			} elseif ($showError) {
				return 'Nothing found!';
			} else {
				return false;
			}
		} elseif ($showError) {
			return 'Please provide a valid dirname.';
		} else {
			return false;
		}
		if ($showError) {
			return 'Failed to fetch release nfo from xREL.to...';
		}
		return false;
	}

	private function getShortUrl($longUrl) {
		$shortUrl = trim(@file_get_contents("http://is.gd/create.php?format=simple&url=" . $longUrl));
		if (!empty($shortUrl) && strpos($shortUrl, "http") !== false) {
			return $shortUrl;
		}
		return $longUrl;
	}

	/**
	 * @param string $msg
	 * @param string $command
	 *
	 * @return string|boolean
	 */
	private function getCommandQuery($msg, $command) {
		if(stringStartsWith(strtolower($msg), $this->config['trigger'] . $command)) {
			$query = str_replace($this->config['trigger'] . $command, "", $msg);
			$query = trim($query);
			return $query;
		} else {
			return false;
		}
	}

	/**
	 * @param string $to
	 * @param string $msg
	 * @param string|array $highlight = NULL
	 */
	private function sendMessage($to, $msg, $highlight = NULL) {
		if ($highlight !== NULL) {
			if (is_array($highlight)) {
				$highlight = join(", ", $highlight);
			}
			if ($highlight !== $to) {
				$msg = $highlight . ": " . $msg;
			}
		}
		sendMessage($this->socket, $to, $msg);
	}
}