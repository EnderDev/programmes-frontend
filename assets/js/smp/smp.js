/**
 * SMP module is responsible to start the smp player. Starting this player means:
 *      - Load user-player-preferences from cookie
 *      - resumeHandler to an specific moment in the video if specified in the URL or in a cooke
 *      - Display button to allow (or not) to embed the video
 *      - Avoid autoplay if specified
 */
define(['jquery-1.9', 'smp/smpStatePersistance', 'bump-3'], function ($, SmpStatePersistance, Bump) {

    var Smp = function (options) {

        var TIMERESUME_VALUE_REGEX = /t=(?:([0-9]{1,2}?)h)?(?:([0-9]{1,3}?)m)?(?:([0-9]{1,4})s)?/;

        this.initial_options = {
                container: null,
                smpSettings: {
                    product: "iplayer",
                    siteID: 'iPlayer',
                    appName: "programmes",
                    appType: "responsive",
                    counterName: null,
                    playerProfile: 'smp',
                    responsive: true,
                    superResponsive: true,
                    allowCasting: true,
                    delayEmbed: false,
                    requestWMP: true,
                    playlistObject: null,
                    ui: {},
                    locale: {
                        lang: 'en-gb'
                    },
                    embed: {
                        enabled: true
                    },
                    statsObject: {
                        deviceId: null,
                        sessionLabels: {}
                    },
                    muted: null,
                    volume: null,
                    autoplay: false
                },
                rememberResume: false,
                messages: {
                    loading: 'Loading player...',
                    error: 'An error occurred',
                    noVersions: 'No available versions'
                },
                markers: [],
                bbcdotcomAdverts: null,
                bbcdotcomAnaglytics: null,
                UAS: null,
                recBump: null
        };

        this.options = $.extend(true, {}, this.initial_options, options);
        this.playerRepostiroy = new SmpStatePersistance(this.options.pid);
        this.bump = Bump;
        this.player = null;

        this.init = function() {
            restoreUserPrefs();
            resumeReproductionTime();
            loadPlayer();
        };

        /*
         * Private methods
         */

        var self  = this;

        var restoreUserPrefs = function() {
            self.options.smpSettings.volume = self.playerRepostiroy.findVolume();
            self.options.smpSettings.muted = self.options.smpSettings.volume === 0;
        };

        var resumeReproductionTime = function() {
            var timeInUrl = getTimeFromUrl();

            if (timeInUrl !== null) {
                self.options.smpSettings.startTime = timeInUrl;
            } else if (self.options.rememberResume) {
                self.options.smpSettings.startTime = self.playerRepostiroy.findTimeResume();
            } else {
                self.options.smpSettings.startTime = 0;
            }
        };

        var initMarkers = function () {
            if (self.markers && self.markers.length > 0) {
                self.options.smpSettings.ui.markers = {enabled: true, hideBelowWidth: 480};
            }

            if (self.player) {
                self.player.setData({name: 'SMP.markers', data: self.markers});
            }
        };

        var loadPlayer = function() {
            setStatsFromRecommendations();
            self.player = self.bump(self.options.container).player(self.options.smpSettings);
            console.log(self.options.smpSettings);
            listenVolumeEvents();
            listenProgressTimeEvents();
            initMarkers();
            self.player.load();
        };

        var getTimeFromUrl = function() {
            var match = TIMERESUME_VALUE_REGEX.exec(window.location.hash);
            if (match !== null) {
                return toSeconds(match);
            }

            return null;
        };

        var toSeconds = function (match) {
            var hours = match[1] ? parseInt(match[1], 10) : 0;
            var mins = match[2] ? parseInt(match[2], 10) : 0;
            var secs = match[3] ? parseInt(match[3], 10) : 0;

            return (hours * 3600) + (mins * 60) + secs;
        };

        var setStatsFromRecommendations = function() {
            if (self.options.recBump) {
                var recStats = self.options.recBump.getRecommendationStats();
                self.options.smpSettings.statsObject.sessionLabels = $.extend(
                    true,
                    {},
                    self.options.smpSettings.statsObject.sessionLabels,
                    recStats
                );
            }
        };

        var listenVolumeEvents = function() {
            self.player.bind('volumechange', function (event) {
                self.playerRepostiroy.updateVolume(event.volume);
            });
        };

        var listenProgressTimeEvents = function ()
        {
            var savedTime = 0;
            var currentTime = 0;

            self.player.bind('playing', function(event) {
                var startTime = 0;

                self.playerInteracted = true;

                if (self.options.smpSettings.startTime) {
                    startTime = self.options.smpSettings.startTime;
                } else if (currentTime) {
                    startTime = currentTime;
                }

                if (self.options.UAS) {
                    self.options.UAS.notifyStarted(Math.floor(startTime));
                }
            });

            self.player.bind('timeupdate', function(event) {
                currentTime = (Math.floor(event.currentTime));

                if (self.options.rememberResume) {
                    // Even if this event is called many times per second, we want to act on it (write to the cookie) every 1 second
                    if (currentTime != savedTime) {
                        self.playerRepostiroy.updateTimeResume(parseInt(currentTime, 10));
                        savedTime = currentTime;
                    }
                }

                if (self.options.UAS) {
                    self.options.UAS.notifyHeartbeat(Math.floor(currentTime));
                }
            });

            self.player.bind('ended', function(event) {
                if (self.options.rememberResume) {
                    self.playerRepostiroy.removeTimeResume();
                }

                if (self.options.UAS) {
                    self.options.UAS.notifyEnded(Math.floor(currentTime));
                }
            });

            self.player.bind('pause', function (event) {
                if (self.options.UAS) {
                    self.options.UAS.notifyPaused(currentTime);
                }
            });
        };
    };

    return Smp;
});
