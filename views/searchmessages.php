<?php defined('APPLICATION') or die; ?>

<div class="SearchForm">
    <?= $this->Form->open(['action' => url('/messages/search'), 'method' => 'post']) ?>
    <?= $this->Form->errors() ?>
    <div class="SiteSearch InputAndButton">
    <?= $this->Form->textBox('Search', ['aria-label' => t('Enter your search term.'), 'title' => t('Enter your search term.')]) ?>
    <?= $this->Form->button('Search', ['aria-label' => t('Search'), 'Name' => '']) ?>
    </div>
    <?= $this->Form->close() ?>
</div>

<?php

decho($this->data('Results'));

/*
Ignore mine [ ]
From Users [____________]
From [_____] To [____]
 */