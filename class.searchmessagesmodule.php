<?php
class SearchMessagesModule extends Gdn_Module {
    public function __construct($sender = '') {
        parent::__construct($sender);
    }

    public function assetTarget() {
        return 'Panel';
    }

    public function toString() {
        $this->Form = new Gdn_Form();
        echo '<div class="Box BoxSearchMessages">';
        echo '<h4>Search in Messages</h4>';
        echo $this->Form->open(['action' => 'search', 'method' => 'post']);
        echo $this->Form->textBox('Search', ['aria-label' => t('Enter your search term.'), 'class' => 'InputBox']);
        echo $this->Form->button('Search', ['aria-label' => t('Search'), 'Name' => '']);
        echo $this->Form->close();
        echo '</div>';
    }
}
