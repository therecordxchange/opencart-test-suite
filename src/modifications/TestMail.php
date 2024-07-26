<?php

class TestMail extends Mail {

    public function getTo()
    {
        return $this->to;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getSender()
    {
        return $this->sender;
    }

    public function getReplyTo()
    {
        return $this->reply_to;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getText()
    {
        return $this->text;
    }

    public function getHtml()
    {
        return $this->html;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function send() : bool
    {
        if (!$this->to) {
            trigger_error('Error: E-Mail to required!');
            exit();
        }

        if (!$this->from) {
            trigger_error('Error: E-Mail from required!');
            exit();
        }

        if (!$this->sender) {
            trigger_error('Error: E-Mail sender required!');
            exit();
        }

        if (!$this->subject) {
            trigger_error('Error: E-Mail subject required!');
            exit();
        }

        if ((!$this->text) && (!$this->html)) {
            trigger_error('Error: E-Mail message required!');
            exit();
        }

        if (is_array($this->to)) {
            $to = implode(',', $this->to);
        } else {
            $to = $this->to;
        }

        return $this;
    }

}
