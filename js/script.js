document.addEventListener("DOMContentLoaded", () => {

    const header = document.querySelector(".site-header");

    const navToggle = document.querySelector(".nav-toggle");
    const navPanel = document.querySelector(".nav-panel");
    const navLinks = document.querySelectorAll(".nav-links a");

    const searchModal = document.querySelector(".search-modal");
    const searchTrigger = document.querySelector(".search-trigger");
    const searchClose = document.querySelector(".search-close");
    const searchBackdrop = document.querySelector(".search-backdrop");

    const searchForm = document.querySelector(".search-form");
    const searchInput = document.querySelector("#site-search");
    const searchResult = document.querySelector(".search-result");

    const mapView = document.querySelector(".map-view");
    const locationToast = document.querySelector("#mapLocationToast");
    const selectedLocation = document.querySelector("#selectedLocation");

    const mapSearch = document.querySelector("#mapSearch");
    const municipalityFilter =
        document.querySelector("#municipalityFilter");

    const soilFilter =
        document.querySelector("#soilFilter");

    const capacityFilter =
        document.querySelector("#capacityFilter");

    const markers =
        [...document.querySelectorAll(".gis-marker")];

    const tableRows =
        [...document.querySelectorAll(".records-table-card tbody tr")];

    const updateHeader = () => {

        if (!header) return;

        header.classList.toggle(
            "scrolled",
            window.scrollY > 20
        );

    };

    updateHeader();

    window.addEventListener(
        "scroll",
        updateHeader,
        { passive: true }
    );

    //mob nav
    navToggle?.addEventListener("click", () => {

        const open =
            navPanel.classList.toggle("open");

        navToggle.setAttribute(
            "aria-expanded",
            String(open)
        );

        const icon =
            navToggle.querySelector("i");

        if (icon) {

            icon.className = open
                ? "fa-solid fa-xmark"
                : "fa-solid fa-bars";

        }

    });



    /* Close mobile navigation */

    navLinks.forEach(link => {

        link.addEventListener("click", () => {

            navPanel?.classList.remove("open");

            navToggle?.setAttribute(
                "aria-expanded",
                "false"
            );

            const icon =
                navToggle?.querySelector("i");

            if (icon) {
                icon.className =
                    "fa-solid fa-bars";
            }

        });

    });

    //act nav
    const sections =
        document.querySelectorAll(
            "main section[id], footer[id]"
        );

    if ("IntersectionObserver" in window) {

        const sectionObserver =
            new IntersectionObserver(
                entries => {

                    entries.forEach(entry => {

                        if (!entry.isIntersecting)
                            return;

                        const id =
                            entry.target.id;

                        navLinks.forEach(link => {

                            link.classList.toggle(
                                "active",
                                link.getAttribute("href") ===
                                `#${id}`
                            );

                        });

                    });

                },
                {
                    rootMargin:
                        "-25% 0px -65% 0px",
                    threshold: 0
                }
            );

        sections.forEach(section => {
            sectionObserver.observe(section);
        });

    }

    //reveal animate
    const revealItems =
        document.querySelectorAll(".reveal");

    if ("IntersectionObserver" in window) {

        const revealObserver =
            new IntersectionObserver(
                entries => {

                    entries.forEach(entry => {

                        if (
                            entry.isIntersecting
                        ) {

                            entry.target.classList.add(
                                "visible"
                            );

                            revealObserver.unobserve(
                                entry.target
                            );

                        }

                    });

                },
                {
                    threshold: 0.1
                }
            );

        revealItems.forEach(item => {
            revealObserver.observe(item);
        });

    } else {

        revealItems.forEach(item => {
            item.classList.add("visible");
        });

    }

    const counterSection =
        document.querySelector(".stats-section");

    const counters =
        document.querySelectorAll(".counter");

    let countersStarted = false;

    const animateCounter = element => {

        const target =
            Number(element.dataset.target);

        const duration = 1300;

        const start =
            performance.now();

        const tick = now => {

            const progress =
                Math.min(
                    (now - start) / duration,
                    1
                );

            const eased =
                1 - Math.pow(
                    1 - progress,
                    3
                );

            element.textContent =
                Math.round(
                    target * eased
                ).toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(tick);
            }

        };

        requestAnimationFrame(tick);

    };

    if (
        counterSection &&
        "IntersectionObserver" in window
    ) {

        const counterObserver =
            new IntersectionObserver(
                entries => {

                    if (
                        entries[0].isIntersecting &&
                        !countersStarted
                    ) {

                        countersStarted = true;

                        counters.forEach(
                            animateCounter
                        );

                        counterObserver.disconnect();

                    }

                },
                {
                    threshold: 0.45
                }
            );

        counterObserver.observe(counterSection);

    }

    //map toast

    let mapToastTimer;

    const showMapToast = message => {

        if (!locationToast)
            return;

        const text =
            locationToast.querySelector("span");

        if (text) {
            text.textContent = message;
        }

        locationToast.classList.add("show");

        clearTimeout(mapToastTimer);

        mapToastTimer =
            setTimeout(() => {

                locationToast.classList.remove(
                    "show"
                );

            }, 1800);

    };

    //map controls
    document
        .querySelectorAll("[data-map-action]")
        .forEach(button => {

            button.addEventListener("click", () => {

                const action =
                    button.dataset.mapAction;

                if (!mapView)
                    return;

                if (action === "zoom-in") {

                    mapView.classList.remove(
                        "zoom-out"
                    );

                    mapView.classList.toggle(
                        "zoom-in"
                    );

                    showMapToast(
                        "Map zoomed in"
                    );

                }

                else if (action === "zoom-out") {

                    mapView.classList.remove(
                        "zoom-in"
                    );

                    mapView.classList.toggle(
                        "zoom-out"
                    );

                    showMapToast(
                        "Map zoomed out"
                    );

                }

                else if (action === "reset") {

                    mapView.classList.remove(
                        "zoom-in",
                        "zoom-out"
                    );

                    showMapToast(
                        "Map view reset"
                    );

                }

            });

        });

    const getMarkerData = marker => {

        return {

            id: marker.dataset.id,

            location:
                marker.dataset.location || "",

            municipality:
                marker.dataset.municipality || "",

            soil:
                marker.dataset.soil || "",

            capacity:
                marker.dataset.capacity || "",

            test:
                marker.dataset.test || "SPT",

            bearing:
                marker.dataset.bearing || "N/A",

            date:
                marker.dataset.date || "N/A",

            coordinates:
                marker.dataset.coordinates || "N/A"

        };

    };

    const capacityLabel = capacity => {

        const labels = {

            "very-high":
                "Very High",

            "high":
                "High",

            "medium":
                "Medium",

            "low":
                "Low",

            "very-low":
                "Very Low"

        };

        return labels[capacity] || "Available";

    };

    const soilLabel = soil => {

        const labels = {

            clay:
                "Clay",

            gravelly_sand:
                "Gravelly Sand",

            sand:
                "Sand",

            sandy_clay:
                "Sandy Clay",

            silty_sand:
                "Silty Sand"

        };

        return labels[soil] || "Not specified";

    };

    //display loc details
    const showLocationDetails = marker => {

        if (!selectedLocation)
            return;

        const data =
            getMarkerData(marker);

        selectedLocation.innerHTML = `

            <div class="location-details">

                <div class="location-title">

                    <i class="fa-solid fa-location-dot"></i>

                    <strong>
                        ${data.location}
                    </strong>

                </div>

                <p class="location-subtitle">
                    ${formatMunicipality(data.municipality)}
                </p>

                <div class="location-value">

                    <span>
                        Soil Type
                    </span>

                    <strong>
                        ${soilLabel(data.soil)}
                    </strong>

                </div>

                <div class="location-value">

                    <span>
                        Investigation
                    </span>

                    <strong>
                        ${data.test}
                    </strong>

                </div>

                <div class="location-value">

                    <span>
                        Bearing Capacity
                    </span>

                    <strong>
                        ${data.bearing} kPa
                    </strong>

                </div>

                <div class="location-value">

                    <span>
                        Classification
                    </span>

                    <strong>
                        ${capacityLabel(data.capacity)}
                    </strong>

                </div>

                <div class="location-value">

                    <span>
                        Test Date
                    </span>

                    <strong>
                        ${data.date}
                    </strong>

                </div>

                <div class="location-value">

                    <span>
                        Coordinates
                    </span>

                    <strong>
                        ${data.coordinates}
                    </strong>

                </div>

            </div>

        `;

        markers.forEach(item => {
            item.classList.remove("selected");
        });

        marker.classList.add("selected");

        showMapToast(
            data.location
        );

    };

    const formatMunicipality = value => {

        const names = {

            sogod:
                "Sogod, Southern Leyte",

            maasin:
                "Maasin City, Southern Leyte",

            "san-juan":
                "San Juan, Southern Leyte",

            hinunangan:
                "Hinunangan, Southern Leyte",

            macrohon:
                "Macrohon, Southern Leyte"

        };

        return names[value] ||
            "Southern Leyte";

    };

    //marker click

    markers.forEach(marker => {

        marker.addEventListener(
            "click",
            () => {

                showLocationDetails(marker);

            }
        );

    });

    const applyMapFilters = () => {

        const search =
            mapSearch?.value
                .trim()
                .toLowerCase() || "";

        const municipality =
            municipalityFilter?.value || "";

        const soil =
            soilFilter?.value || "";

        const capacity =
            capacityFilter?.value || "";

        let visibleCount = 0;

        markers.forEach(marker => {

            const data =
                getMarkerData(marker);

            const searchableText =
                `${data.location}
                ${formatMunicipality(data.municipality)}
                ${soilLabel(data.soil)}
                ${data.test}
                ${data.bearing}`.toLowerCase();

            const matchesSearch =
                !search ||
                searchableText.includes(search);

            const matchesMunicipality =
                !municipality ||
                data.municipality === municipality;

            const matchesSoil =
                !soil ||
                data.soil === soil;

            const matchesCapacity =
                !capacity ||
                data.capacity === capacity;

            const visible =
                matchesSearch &&
                matchesMunicipality &&
                matchesSoil &&
                matchesCapacity;

            marker.style.display =
                visible ? "block" : "none";

            if (visible) {
                visibleCount++;
            }

        });



        /* Filter table rows */

        tableRows.forEach(row => {

            const text =
                row.textContent.toLowerCase();

            const visible =
                !search ||
                text.includes(search);

            row.style.display =
                visible ? "" : "none";

        });



        if (
            search ||
            municipality ||
            soil ||
            capacity
        ) {

            showMapToast(
                `${visibleCount} location${visibleCount !== 1 ? "s" : ""} found`
            );

        } else {

            showMapToast(
                "Showing all soil locations"
            );

        }

    };

    // map search
    mapSearch?.addEventListener(
        "input",
        applyMapFilters
    );


    municipalityFilter?.addEventListener(
        "change",
        applyMapFilters
    );

    soilFilter?.addEventListener(
        "change",
        applyMapFilters
    );

    capacityFilter?.addEventListener(
        "change",
        applyMapFilters
    );

    //global s modal
    const openSearch = () => {

        if (!searchModal)
            return;

        searchModal.classList.add("open");

        searchModal.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body.classList.add(
            "modal-open"
        );

        setTimeout(() => {

            searchInput?.focus();

        }, 100);

    };



    const closeSearch = () => {

        if (!searchModal)
            return;

        searchModal.classList.remove("open");

        searchModal.setAttribute(
            "aria-hidden",
            "true"
        );

        document.body.classList.remove(
            "modal-open"
        );

        if (searchResult) {
            searchResult.textContent = "";
        }

    };



    searchTrigger?.addEventListener(
        "click",
        openSearch
    );

    searchClose?.addEventListener(
        "click",
        closeSearch
    );

    searchBackdrop?.addEventListener(
        "click",
        closeSearch
    );

    /* ESC closes search */

    document.addEventListener(
        "keydown",
        event => {

            if (
                event.key === "Escape" &&
                searchModal?.classList.contains(
                    "open"
                )
            ) {

                closeSearch();

            }

        }
    );
    //global search 
    searchForm?.addEventListener(
        "submit",
        event => {

            event.preventDefault();

            const term =
                searchInput?.value
                    .trim()
                    .toLowerCase();

            if (!term) {

                searchResult.textContent =
                    "Enter a municipality, barangay, soil type, or keyword.";

                return;

            }



            const matches =
                markers.filter(marker => {

                    const data =
                        getMarkerData(marker);

                    const text =
                        `${data.location}
                        ${data.municipality}
                        ${soilLabel(data.soil)}
                        ${data.test}
                        ${data.bearing}
                        ${data.date}`.toLowerCase();

                    return text.includes(term);

                });



            if (matches.length === 0) {

                searchResult.innerHTML = `

                    <i class="fa-solid fa-circle-exclamation"></i>

                    No soil records found for
                    <strong>"${escapeHTML(term)}"</strong>.

                `;

                return;

            }



            const firstMatch =
                matches[0];

            const data =
                getMarkerData(firstMatch);



            searchResult.innerHTML = `

                <i class="fa-solid fa-circle-check"></i>

                Found
                <strong>${matches.length}</strong>
                matching record${matches.length > 1 ? "s" : ""}.

            `;

            /* Close modal */

            setTimeout(() => {

                closeSearch();

                /* Apply search to map */

                if (mapSearch) {
                    mapSearch.value = term;
                }

                applyMapFilters();



                /* Scroll to map */

                document
                    .querySelector("#map")
                    ?.scrollIntoView({
                        behavior: "smooth"
                    });


                /* Show first matching record */

                setTimeout(() => {

                    showLocationDetails(
                        firstMatch
                    );

                }, 700);

            }, 700);

        }
    );  

    const escapeHTML = value => {

        const div =
            document.createElement("div");

        div.textContent = value;

        return div.innerHTML;

    };

    // clear map filters
    const clearMapFilters = () => {

        if (mapSearch)
            mapSearch.value = "";

        if (municipalityFilter)
            municipalityFilter.value = "";

        if (soilFilter)
            soilFilter.value = "";

        if (capacityFilter)
            capacityFilter.value = "";

        applyMapFilters();

    };

    //reset
    document
        .querySelector("[data-clear-map-filters]")
        ?.addEventListener(
            "click",
            clearMapFilters
        );

    //anchor nav
    document
        .querySelectorAll('a[href^="#"]')
        .forEach(link => {

            link.addEventListener(
                "click",
                event => {

                    const targetId =
                        link.getAttribute("href");

                    if (
                        !targetId ||
                        targetId === "#"
                    )
                        return;

                    const target =
                        document.querySelector(
                            targetId
                        );

                    if (!target)
                        return;

                    event.preventDefault();

                    target.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });

                }
            );

        });

// initial map

    applyMapFilters();

});