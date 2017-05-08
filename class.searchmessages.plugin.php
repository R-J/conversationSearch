<?php
$PluginInfo['searchMessages'] = [
    'Name' => 'Search Messages',
    'Description' => 'Allows searching in messages.',
    'Version' => '0.1',
    'RequiredApplications' => [
        'Vanilla' => '>= 2.3',
        'Conversations' => '>= 2.3'
    ],
    'SettingsPermission' => 'Garden.Settings.Manage',
    // 'SettingsUrl' => '/dashboard/settings/searchmessages',
    // 'RegisterPermissions' => ['searchmessages.Add'],
    'MobileFriendly' => true,
    'HasLocale' => true,
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/r_j',
    'License' => 'MIT'
];

class SearchMessagesPlugin extends Gdn_Plugin {
    public function setup() {
        $this->structure();
    }

    public function structure() {
        Gdn::structure()
            ->table('Conversation')
            ->column('Subject', 'varchar(255)', null, 'fulltext')
            ->set();
        Gdn::structure()
            ->table('ConversationMessage')
            ->column('Body', 'text', false, 'fulltext')
            ->set();
    }

    public function messagesController_render_before($sender) {
        // Don't show module if we are already on the search page.
        if ($sender->RequestMethod == 'search') {
            return;
        }
        $searchMessagesModule = new SearchMessagesModule();
        $sender->addModule($searchMessagesModule);
    }

    public function messagesController_search_create($sender, $args) {
        // Gdn_Theme::section('SearchResults');
        Gdn_Theme::section('Conversation');

        // Basic page setup.
        $sender->title(t('Search Messages'));
        $sender->addBreadcrumb('Search', '/messages/search');
        $sender->View = $sender->fetchViewLocation('searchmessages', '', 'plugins/searchMessages');

        // Deliver json data if necessary
        if ($sender->deliveryType() != DELIVERY_TYPE_ALL) {
            $sender->setJson('LessRow', $sender->Pager->toString('less'));
            $sender->setJson('MoreRow', $sender->Pager->toString('more'));
            $sender->View = 'searchmessages';
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
        $searchModel = new SearchMessagesModel();
        $searchModel->addSearch($this->messageSql($searchModel));

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





    public function messageSql($searchModel, $addMatch = true) {
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
