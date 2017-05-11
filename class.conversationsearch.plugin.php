<?php
$PluginInfo['conversationSearch'] = [
    'Name' => 'Conversation Search',
    'Description' => 'Allows searching in conversations.',
    'Version' => '0.0.9',
    'RequiredApplications' => [
        'Vanilla' => '>= 2.3',
        'Conversations' => '>= 2.3'
    ],
    'SettingsPermission' => 'Garden.Settings.Manage',
    'MobileFriendly' => true,
    'HasLocale' => true,
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/r_j',
    'License' => 'MIT'
];

class ConversationSearchPlugin extends Gdn_Plugin {
    /**
     * [setup description]
     * @return [type] [description]
     */
    public function setup() {
        $this->structure();
    }

    /**
     * [structure description]
     * @return [type] [description]
     */
    public function structure() {
        $versionInfo = explode('.', Gdn::sql()->version());
        $px = Gdn::database()->DatabasePrefix;

        $tables = [
            ['Name' => 'Conversation', 'FulltextColumn' => 'Subject'],
            ['Name' => 'ConversationMessage', 'FulltextColumn' => 'Body']
        ];

        foreach($tables as $table) {
            $tableName = Gdn::database()->DatabasePrefix.$table['Name'];
            $tableName = Gdn::sql()->formatTableName($tableName);

            // InnoDB in MySQL < 5.6 doesn't support fulltext, so force MyISAM.
            if (!($versionInfo[0] >= 5 && $versionInfo[1] >= 6)) {
                $sql = "ALTER TABLE `{$tableName}` ENGINE = MyISAM; ";
                Gdn::structure()->query($sql);
            }
            // Get all indexes of current table.
            $indexes = Gdn::structure()
                ->query("SHOW INDEXES FROM `{$tableName}`")
                ->resultObject();
            // Loop through indexes to determine of column already has fulltext index.
            $indexExists = false;
            foreach($indexes as $index) {
                if (
                    $index->Column_name == $table['FulltextColumn'] &&
                    $index->Index_type == 'FULLTEXT'
                ) {
                    $indexExists = true;
                }
            }
            if (!$indexExists) {
                $keyName = "TX_{$table['Name']}_ConversationSearch";
                $sql = "ALTER TABLE `{$tableName}` ";
                $sql .= "ADD FULLTEXT $keyName (`{$table['FulltextColumn']}`); ";
                Gdn::structure()->query($sql);
            }
        }


        return;
        /**
         * The Easiest way isn't working because InnoDBs fulltext
         * capabilities are not supported  yet.
         */
        /*
        Gdn::database()->structure()
            ->table('Conversation')
            ->column('Subject', 'varchar(255)', null, 'fulltext')
            ->set();
        Gdn::database()->structure()
            ->table('ConversationMessage')
            ->column('Body', 'text', false, 'fulltext')
            ->set();
        */
    }

    /**
     * [messagesController_render_before description]
     * @param  [type] $sender [description]
     * @return [type]         [description]
     */
    public function messagesController_render_before($sender) {
        // Don't show module if we are already on the search page.
        if ($sender->RequestMethod == 'search') {
            return;
        }
        $conversationSearchModule = new ConversationSearchModule();
        $sender->addModule($conversationSearchModule);
    }

    /**
     * [messagesController_search_create description]
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function messagesController_search_create($sender, $args) {
        // Gdn_Theme::section('SearchResults');
        Gdn_Theme::section('Conversation');

        // Basic page setup.
        $sender->title(t('Search Conversations'));
        $sender->addBreadcrumb('Search', '/messages/search');
        $sender->View = $sender->fetchViewLocation('conversationsearch', '', 'plugins/conversationSearch');

        // Deliver json data if necessary
        if ($sender->deliveryType() != DELIVERY_TYPE_ALL) {
            $sender->setJson('LessRow', $sender->Pager->toString('less'));
            $sender->setJson('MoreRow', $sender->Pager->toString('more'));
            $sender->View = 'conversationsearch';
        }

        $formValues = $sender->Request->post();
        $sender->Form->setData($formValues);
        $search = val('Search', $formValues);

        // Check if form is displayed first time (without search terms).
        if (!$sender->Form->authenticatedPostBack() || $search == '') {
            $sender->render();
        }

        // get only a subset of results.
        if (array_key_exists(0, $args)) {
            $page = $args[0];
        } else {
            $page = '';
        }
        list($offset, $limit) = offsetLimit($page, c('Garden.Search.PerPage', 20));
        $sender->setData('_Limit', $limit);

        // $searchModel = new SearchModel();
        $searchModel = new ConversationSearchModel();
/*
saveToConfig('conversationSearch.SearchUnread', true, false);
$searchModel->searchUnread = true;
*/

        $mode = val('Mode', $formValues);
        if ($mode) {
            $searchModel->ForceSearchMode = $mode;
        }
        try {
            $resultSet = $searchModel->search($search, $offset, $limit);
        } catch (Gdn_UserException $Ex) {
            $sender->Form->addError($Ex);
            $resultSet = [];
        } catch (Exception $Ex) {
            logException($Ex);
            $sender->Form->addError($Ex);
            $resultSet = [];
        }

        $sender->setData('Results', $resultSet);
        $sender->setData('Search',  $search);

        /*
        // Build a pager
        $pagerFactory = new Gdn_PagerFactory();
        $sender->Pager = $PagerFactory->getPager('MorePager', $sender);
        $sender->Pager->MoreCode = 'Newer Messages';
        $sender->Pager->LessCode = 'Older Messages';
        $sender->Pager->ClientID = 'Pager';
        $sender->Pager->configure(
            $sender->Offset,
            $Limit,
            $sender->Conversation->CountMessages,
            'messages/'.$ConversationID.'/%1$s/%2$s/'
        );
        */


        // Render view
        $sender->render();
    }

    /**
     * [conversationSql description]
     * @param  [type]  $searchModel [description]
     * @param  boolean $addMatch    [description]
     * @return [type]               [description]
     */
    public function conversationSql($searchModel, $addMatch = true) {
        // Restrict to own conversations!
        if ($addMatch) {
            // Build search part of query
            $searchModel->addMatchSql($searchModel->SQL, 'cm.Body', 'cm.DateInserted');
        }

        // Build base query
        $searchModel->SQL
            ->select('cm.MessageID as PrimaryID, c.Subject as Title, cm.Body as Summary, cm.Format')
            ->select("'messages/', cm.ConversationID, '#Message_', cm.MessageID", "concat", 'Url')
            ->select('cm.DateInserted')
            ->select('cm.InsertUserID as UserID')
            ->select("'Message'", '', 'RecordType')
            ->from('ConversationMessage cm')
            ->join('Conversation c', 'c.ConversationID = cm.ConversationID');

        if ($addMatch) {
            // Exectute query
            $result = $searchModel->SQL->getSelect();

            // Unset SQL
            $searchModel->SQL->reset();
        } else {
            $result = $searchModel->SQL;
        }

        return $result;
    }
}
