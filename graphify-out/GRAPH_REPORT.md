# Graph Report - divaraap  (2026-10-14)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 2522 nodes · 5157 edges · 264 communities (66 shown, 198 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 180 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `013af05a`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 78
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 95
- Community 97
- Community 98
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 116
- Community 117
- Community 118
- Community 119
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 170
- Community 171
- Community 172
- Community 173
- Community 174
- Community 175
- Community 176
- Community 177
- Community 178
- Community 179
- Community 180
- Community 181
- Community 182
- Community 183
- Community 184
- Community 185
- Community 186
- Community 187
- Community 188
- Community 189
- Community 190
- Community 191
- Community 192

## God Nodes (most connected - your core abstractions)
1. `Listing` - 126 edges
2. `User` - 116 edges
3. `Category` - 75 edges
4. `Video` - 67 edges
5. `PanelQuickListingForm` - 60 edges
6. `Conversation` - 43 edges
7. `Offer` - 40 edges
8. `Controller` - 39 edges
9. `compilerOptions` - 34 edges
10. `DemoSchemaManager` - 33 edges

## Surprising Connections (you probably didn't know these)
- `DemoController` --inherits--> `Controller`  [EXTRACTED]
  Modules/Demo/App/Http/Controllers/DemoController.php → app/Http/Controllers/Controller.php
- `ConversationController` --inherits--> `Controller`  [EXTRACTED]
  Modules/Conversation/App/Http/Controllers/ConversationController.php → app/Http/Controllers/Controller.php
- `LocationLookupController` --inherits--> `Controller`  [EXTRACTED]
  Modules/Location/Http/Controllers/LocationLookupController.php → app/Http/Controllers/Controller.php
- `PanelController` --inherits--> `Controller`  [EXTRACTED]
  Modules/Panel/App/Http/Controllers/PanelController.php → app/Http/Controllers/Controller.php
- `{closure#1}()` --references--> `User`  [EXTRACTED]
  app/Support/UserDirectory.php → Modules/User/App/Models/User.php

## Import Cycles
- None detected.

## Communities (264 total, 198 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (10): CleanupDemoCommand, PrepareDemoCommand, DemoController, ResolveDemoRequest, DemoInstance, DemoServiceProvider, DemoSchemaManager, TurnstileVerifier (+2 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (8): ConversationReadUpdated, InboxMessageCreated, {closure#1}(), ConversationController, Conversation, ConversationMessage, QuickMessageCatalog, LocationLookupController

### Community 2 - "Community 2"
Cohesion: 0.08
Nodes (9): ActiveListingStatus, ExpiredListingStatus, ListingStatus, PendingListingStatus, SoldListingStatus, ActiveUserStatus, BannedUserStatus, SuspendedUserStatus (+1 more)

### Community 3 - "Community 3"
Cohesion: 0.07
Nodes (15): CategoryResource, ListingCustomFieldResource, ListingResource, AdminListingResourceSchema, CityResource, CountryResource, DistrictResource, UserNotificationResource (+7 more)

### Community 4 - "Community 4"
Cohesion: 0.11
Nodes (4): ResourceTableActions, ResourceTableColumns, {closure#1}(), {closure#1}()

### Community 5 - "Community 5"
Cohesion: 0.07
Nodes (14): EditCategory, EditListingCustomField, EditListing, EditCity, EditCountry, EditDistrict, EditUserNotificationResourceRecord, EditOfferResourceRecord (+6 more)

### Community 6 - "Community 6"
Cohesion: 0.06
Nodes (5): {closure#1}(), PromotionOrder, MarketplaceFlowTest, PanelSmokeTest, TestCase

### Community 7 - "Community 7"
Cohesion: 0.08
Nodes (13): ListListingCustomFields, ListListings, ListCities, ListCountries, ListDistricts, ListUserNotificationResourceRecords, ListOfferResourceRecords, ListPageResourceRecords (+5 more)

### Community 8 - "Community 8"
Cohesion: 0.07
Nodes (3): {closure#2}(), Listing, {closure#13}()

### Community 9 - "Community 9"
Cohesion: 0.08
Nodes (9): ListingDirectory, UserDirectory, PageController, PromotionController, ReportController, ReviewController, SellerController, {closure#8}() (+1 more)

### Community 10 - "Community 10"
Cohesion: 0.07
Nodes (12): CreateListing, City, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#8}() (+4 more)

### Community 11 - "Community 11"
Cohesion: 0.07
Nodes (17): {closure#10}(), {closure#11}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#18}(), {closure#19}() (+9 more)

### Community 12 - "Community 12"
Cohesion: 0.08
Nodes (13): {closure#1}(), {closure#11}(), {closure#13}(), {closure#14}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+5 more)

### Community 13 - "Community 13"
Cohesion: 0.09
Nodes (5): PanelController, StoreVideoRequest, UpdateListingRequest, UpdateVideoRequest, RegisterRequest

### Community 14 - "Community 14"
Cohesion: 0.08
Nodes (3): {closure#1}(), {closure#1}(), User

### Community 15 - "Community 15"
Cohesion: 0.05
Nodes (36): compilerOptions, allowUnreachableCode, allowUnusedLabels, alwaysStrict, baseUrl, esModuleInterop, exactOptionalPropertyTypes, forceConsistentCasingInFileNames (+28 more)

### Community 16 - "Community 16"
Cohesion: 0.06
Nodes (30): devDependencies, eslint, @eslint/js, globals, laravel-echo, laravel-vite-plugin, pusher-js, @types/node (+22 more)

### Community 17 - "Community 17"
Cohesion: 0.08
Nodes (21): {closure#1}(), {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), {closure#9}(), {closure#1}(), {closure#2}() (+13 more)

### Community 19 - "Community 19"
Cohesion: 0.09
Nodes (9): Controller, HomeController, LanguageController, PublicMediaController, GET /dashboard(), ConfirmPasswordController, ForgotPasswordController, PasswordController (+1 more)

### Community 21 - "Community 21"
Cohesion: 0.14
Nodes (5): FavoriteController, LoginController, RegisterController, AuthProviderCatalog, AuthRedirector

### Community 22 - "Community 22"
Cohesion: 0.11
Nodes (18): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#1}() (+10 more)

### Community 23 - "Community 23"
Cohesion: 0.11
Nodes (9): Video, {closure#11}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+1 more)

### Community 24 - "Community 24"
Cohesion: 0.13
Nodes (5): {closure#3}(), {closure#1}(), {closure#2}(), OfferController, Offer

### Community 25 - "Community 25"
Cohesion: 0.07
Nodes (28): require, a909m/filament-statefusion, ariaieboy/filament-currency, bezhansalleh/filament-language-switch, cheesegrits/filament-google-maps, dutchcodingcompany/filament-developer-logins, dutchcodingcompany/filament-socialite, filament/filament (+20 more)

### Community 26 - "Community 26"
Cohesion: 0.11
Nodes (13): CreateCategory, CreateListingCustomField, CreateCity, CreateCountry, CreateDistrict, CreateUserNotificationResourceRecord, CreateOfferResourceRecord, CreatePageResourceRecord (+5 more)

### Community 27 - "Community 27"
Cohesion: 0.16
Nodes (21): behaviors, boot(), Behavior, defineBehavior(), isMounted(), markMounted(), mounted, startBehaviors() (+13 more)

### Community 31 - "Community 31"
Cohesion: 0.15
Nodes (6): up(), {closure#1}(), DemoStoragePath, {closure#12}(), LocalMedia, {closure#7}()

### Community 32 - "Community 32"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#3}(), {closure#6}(), ListingCustomField, {closure#1}(), ListingCustomFieldSchemaBuilder

### Community 33 - "Community 33"
Cohesion: 0.17
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), ManageGeneralSettings

### Community 34 - "Community 34"
Cohesion: 0.13
Nodes (5): CategoryController, ListingController, {closure#1}(), ThemeServiceProvider, ThemeManager

### Community 35 - "Community 35"
Cohesion: 0.12
Nodes (7): {closure#2}(), {closure#3}(), {closure#4}(), {closure#8}(), Category, {closure#2}(), {closure#4}()

### Community 36 - "Community 36"
Cohesion: 0.10
Nodes (3): {closure#1}(), Review, {closure#1}()

### Community 37 - "Community 37"
Cohesion: 0.16
Nodes (4): EmailVerificationController, SocialAuthController, ProfileController, {closure#1}()

### Community 39 - "Community 39"
Cohesion: 0.18
Nodes (6): DatabaseSeeder, CategorySeeder, DemoContentSeeder, ListingCustomFieldSeeder, PromotionOrderSeeder, PromotionPlanSeeder

### Community 40 - "Community 40"
Cohesion: 0.14
Nodes (16): FavoriteToggleResponse, InboxMessagePayload, InboxState, InboxThreadState, LocationOption, favoriteToggle, isFavoriteResponse(), channelName() (+8 more)

### Community 41 - "Community 41"
Cohesion: 0.14
Nodes (6): {closure#1}(), VideoStatus, Failed, Pending, Processing, Ready

### Community 42 - "Community 42"
Cohesion: 0.12
Nodes (7): {closure#10}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#8}(), {closure#9}()

### Community 43 - "Community 43"
Cohesion: 0.16
Nodes (3): FavoriteListing, FavoriteSeller, SocialiteUser

### Community 44 - "Community 44"
Cohesion: 0.15
Nodes (4): AdminServiceProvider, OfferServiceProvider, PageServiceProvider, ReportServiceProvider

### Community 45 - "Community 45"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#2}(), FavoriteDemoSeeder

### Community 49 - "Community 49"
Cohesion: 0.13
Nodes (14): autoload-dev, psr-4, description, extra, laravel, keywords, dont-discover, license (+6 more)

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (6): {closure#1}(), {closure#10}(), {closure#5}(), {closure#6}(), {closure#8}(), {closure#9}()

### Community 51 - "Community 51"
Cohesion: 0.17
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), {closure#1}()

### Community 53 - "Community 53"
Cohesion: 0.21
Nodes (5): DemoUserCatalog, AuthUserSeeder, {closure#1}(), {closure#2}(), VideoDemoSeeder

### Community 54 - "Community 54"
Cohesion: 0.21
Nodes (11): attribute(), booleanAttribute(), ElementConstructor, focusFirst(), numericAttribute(), queryAll(), appendBubble(), inboxThread (+3 more)

### Community 55 - "Community 55"
Cohesion: 0.25
Nodes (12): buildBody(), csrfToken(), get(), HttpError, HttpMethod, HttpRequest, post(), request() (+4 more)

### Community 58 - "Community 58"
Cohesion: 0.18
Nodes (3): {closure#2}(), {closure#6}(), QuickListingCategorySuggester

### Community 59 - "Community 59"
Cohesion: 0.18
Nodes (3): AppServiceProvider, {closure#2}(), {closure#3}()

### Community 61 - "Community 61"
Cohesion: 0.19
Nodes (3): {closure#1}(), {closure#2}(), SetLocale

### Community 63 - "Community 63"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 66 - "Community 66"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 68 - "Community 68"
Cohesion: 0.23
Nodes (6): ListCategoryActivities, ListListingActivities, ListCityActivities, ListCountryActivities, ListDistrictActivities, ListUserActivities

### Community 72 - "Community 72"
Cohesion: 0.17
Nodes (11): backToRootCategories, enterCategory({{ $category[, goToCategoryStep, goToDetailsStep, goToPreviewStep, goToStep({{ $currentStep - 1 }}), goToStep({{ $number }}), publishListing (+3 more)

### Community 75 - "Community 75"
Cohesion: 0.18
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 76 - "Community 76"
Cohesion: 0.32
Nodes (10): isStoredLocation(), readJson(), StoredLocation, writeJson(), buildCitiesUrl(), label(), locationPicker, normalize() (+2 more)

### Community 80 - "Community 80"
Cohesion: 0.22
Nodes (3): {closure#4}(), {closure#5}(), Country

### Community 81 - "Community 81"
Cohesion: 0.29
Nodes (9): setHidden(), setText(), isLocationOptionList(), characterCounter, confirmAction, dependentSelect, imagePreview, revealPanel (+1 more)

### Community 82 - "Community 82"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 83 - "Community 83"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 87 - "Community 87"
Cohesion: 0.25
Nodes (8): pestphp/pest-plugin, php-http/discovery, wikimedia/composer-merge-plugin, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 90 - "Community 90"
Cohesion: 0.36
Nodes (4): {closure#5}(), {closure#6}(), {closure#7}(), ListCategories

### Community 106 - "Community 106"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 108 - "Community 108"
Cohesion: 0.33
Nodes (6): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\, Modules\\

### Community 113 - "Community 113"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 119 - "Community 119"
Cohesion: 0.40
Nodes (5): query(), ContactResponse, contactReveal, isContactResponse(), shareAction

## Knowledge Gaps
- **58 isolated node(s):** `php`, `site::layouts.app`, `panel-quick-listing-form`, `user::auth.partials.social-buttons`, `user::layouts.auth` (+53 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 837 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **198 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Listing` connect `Community 8` to `Community 1`, `Community 3`, `Community 5`, `Community 6`, `Community 9`, `Community 10`, `Community 11`, `Community 12`, `Community 13`, `Community 14`, `Community 17`, `Community 19`, `Community 21`, `Community 23`, `Community 29`, `Community 30`, `Community 34`, `Community 38`, `Community 39`, `Community 42`, `Community 43`, `Community 45`, `Community 50`, `Community 53`, `Community 70`, `Community 93`, `Community 95`, `Community 97`, `Community 110`, `Community 112`, `Community 114`, `Community 115`, `Community 116`, `Community 117`?**
  _High betweenness centrality (0.121) - this node is a cross-community bridge._
- **What connects `php`, `site::layouts.app`, `panel-quick-listing-form` to the rest of the system?**
  _58 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.05126452494873548 - nodes in this community are weakly interconnected._
- **Why does `User` connect `Community 14` to `Community 0`, `Community 1`, `Community 129`, `Community 3`, `Community 6`, `Community 8`, `Community 9`, `Community 10`, `Community 12`, `Community 13`, `Community 17`, `Community 19`, `Community 21`, `Community 23`, `Community 31`, `Community 37`, `Community 38`, `Community 43`, `Community 45`, `Community 52`, `Community 53`, `Community 56`, `Community 60`, `Community 70`, `Community 73`, `Community 79`, `Community 85`, `Community 88`, `Community 93`, `Community 97`, `Community 112`, `Community 114`, `Community 115`, `Community 116`, `Community 117`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.05672926447574335 - nodes in this community are weakly interconnected._
- **Why does `Category` connect `Community 35` to `Community 4`, `Community 7`, `Community 10`, `Community 11`, `Community 12`, `Community 19`, `Community 21`, `Community 30`, `Community 32`, `Community 34`, `Community 38`, `Community 39`, `Community 42`, `Community 43`, `Community 45`, `Community 47`, `Community 48`, `Community 52`, `Community 56`, `Community 58`, `Community 62`, `Community 69`, `Community 70`, `Community 71`, `Community 85`, `Community 90`, `Community 114`?**
  _High betweenness centrality (0.070) - this node is a cross-community bridge._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.07811447811447811 - nodes in this community are weakly interconnected._