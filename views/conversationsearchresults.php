<?php defined('APPLICATION') or die;

$results = $this->data('Results');

if (count($results) == 0) {
    echo '<p class="NoResults">', sprintf(t('No results for %s.', 'No results for <b>%s</b>.'), $search), '</p>';
} else {
?>
    <ol id="search-results" class="DataList DataList-Search">
        <?php foreach ($results as $result): ?>
            <li class="Item Item-Search">
                <h3><?= anchor(htmlspecialchars($result['Title']), $result['Url']); ?></h3>
                <div class="Item-Body Media">
                    <?php
                    $Photo = userPhoto($result, array('LinkClass' => 'Img'));
                    if ($Photo) {
                        echo $Photo;
                    }
                    ?>
                    <div class="Media-Body">
                        <div class="Meta">
                            <?php
                            echo ' <span class="MItem-Author">'.
                                sprintf(t('by %s'), userAnchor($result)).
                                '</span>';

                            echo Bullet(' ');
                            echo ' <span class="MItem-DateInserted">'.
                                Gdn_Format::date($result['DateInserted'], 'html').
                                '</span> ';
                            ?>
                        </div>
                        <div class="Summary">
                            <?php echo $result['Summary']; ?>
                        </div>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

<?php
echo '<div class="PageControls Bottom">';

$RecordCount = $this->data('RecordCount');
if ($RecordCount) {
    echo '<span class="Gloss">'.plural($RecordCount, '%s result', '%s results').'</span>';
}

// PagerModule::write(array('Wrapper' => '<div %1$s>%2$s</div>'));

echo '</div>';

}
