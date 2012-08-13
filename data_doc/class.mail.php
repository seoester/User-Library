<?php
//     This small libary helps you to integrate user managment into your website.
//     Copyright (C) 2011  Seoester <seoester@googlemail.com>
//
//     This program is free software: you can redistribute it and/or modify
//     it under the terms of the GNU General Public License as published by
//     the Free Software Foundation, either version 3 of the License, or
//     (at your option) any later version.
//
//     This program is distributed in the hope that it will be useful,
//     but WITHOUT ANY WARRANTY; without even the implied warranty of
//     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//     GNU General Public License for more details.
//
//     You should have received a copy of the GNU General Public License
//     along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
* The mail class helps abstracting a mail functionality, this basic class provides abstraction for the php mail() function.
*
* @package utils
*/

class Mail {
	public $from;
	public $fromName;
	public $to;
	public $cc;
	public $bcc;
	public $subject;
	public $body;
	public $replyTo;
	public $replyToName;
	public $html = false;
	public $oneMailToEach = false;

	/**
	* Sends the mail(s) configured with the class attributes.
	*
	* @return boolean Whether all mails were be sent successfully
	*/
	public function send() {
		$replyToString = "";
		if (isset($this->replyTo))
			$replyToString = isset($this->replyToName)? sprintf('%s <%s>', $this->replyToName, $this->replyTo) : $this->replyTo;
		if (strlen($replyToString) > 0)
			$replyToString = "Reply-To: $replyToString\r\n";

		$fromString = "";
		if (isset($this->from))
			$fromString = isset($this->fromName)? sprintf('%s <%s>', $this->fromName, $this->from) : $this->from;
		if (strlen($fromString) > 0)
			$fromString = "From: $fromString\r\n";

		$ccString = "";
		if (isset($this->cc))
			$ccString = is_array($this->cc)? implode(",", $this->cc) : $this->cc;
		if (strlen($ccString) > 0)
			$ccString = "CC: $ccString\r\n";

		$bccString = "";
		if (isset($this->bcc))
			$bccString = is_array($this->bcc)? implode(",", $this->bcc) : $this->bcc;
		if (strlen($bccString) > 0)
			$bccString = "BCC: $bccString\r\n";

		$headers = $fromString . $ccString . $bccString . $replyToString;

		if ($this->html)
			$headers .= "MIME-Version: 1.0\r\nContent-Type: text/html;charset=iso-8859-1";

		$success = true;
		if ($this->oneMailToEach) {
			if (is_array($this->to)) {
				foreach ($this->to as $to)
					$success = $success && mail($to, $this->subject, $this->body, $headers);
			} else
			$success = $success && mail($this->to, $this->subject, $this->body, $headers);
		} else {
			$toString = is_array($this->to)? implode(",", $this->to) : $this->to;
			$success = $success && mail($toString, $this->subject, $this->body, $headers);
		}
		return $success;
	}
}
?>