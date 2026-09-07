document.addEventListener("DOMContentLoaded", () => {


    const header =
        document.querySelector(".site-header");

    const navToggle =
        document.querySelector(".nav-toggle");

    const navPanel =
        document.querySelector(".nav-panel");

    const navLinks =
        document.querySelectorAll(".nav-links a");

    const searchModal =
        document.querySelector(".search-modal");

    const searchTrigger =
        document.querySelector(".search-trigger");

    const searchClose =
        document.querySelector(".search-close");

    const searchBackdrop =
        document.querySelector(".search-backdrop");

    const searchForm =
        document.querySelector(".search-form");

    const searchInput =
        document.querySelector("#site-search");

    const searchResult =
        document.querySelector(".search-result");


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


    navToggle?.addEventListener(
        "click",
        () => {

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
        }
    );


    navLinks.forEach(link => {

        link.addEventListener(
            "click",
            () => {

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
            }
        );

    });

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
                        "-30% 0px -60% 0px",
                    threshold: 0
                }
            );

        sections.forEach(section => {

            sectionObserver.observe(section);

        });
    }


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

        counterObserver.observe(
            counterSection
        );
    }


    const fakeMap =
        document.querySelector(".map-view");

    const locationToast =
        document.querySelector(
            "#mapLocationToast"
        );

    const selectedLocation =
        document.querySelector(
            "#selectedLocation"
        );

    let mapToastTimer;


    const showMapToast = message => {

        if (!locationToast)
            return;

        locationToast.querySelector(
            "span"
        ).textContent = message;

        locationToast.classList.add(
            "show"
        );

        clearTimeout(
            mapToastTimer
        );

        mapToastTimer =
            setTimeout(
                () => {

                    locationToast.classList.remove(
                        "show"
                    );

                },
                1800
            );
    };


    /* Map controls */

    document
        .querySelectorAll(
            "[data-map-action]"
        )
        .forEach(button => {

            button.addEventListener(
                "click",
                () => {

                    const action =
                        button.dataset.mapAction;

                    if (!fakeMap)
                        return;


                    if (
                        action === "zoom-in"
                    ) {

                        fakeMap.classList.remove(
                            "zoom-out"
                        );

                        fakeMap.classList.toggle(
                            "zoom-in"
                        );

                        showMapToast(
                            "Map zoomed in"
                        );
                    }


                    else if (
                        action === "zoom-out"
                    ) {

                        fakeMap.classList.remove(
                            "zoom-in"
                        );

                        fakeMap.classList.toggle(
                            "zoom-out"
                        );

                        showMapToast(
                            "Map zoomed out"
                        );
                    }


                    else if (
                        action === "reset"
                    ) {

                        fakeMap.classList.remove(
                            "zoom-in",
                            "zoom-out"
                        );

                        showMapToast(
                            "Map view reset"
                        );
                    }

                }
            );

        });


    /* Map markers */

    const markers =
        document.querySelectorAll(
            ".gis-marker"
        );


    markers.forEach(marker => {

        marker.addEventListener(
            "click",
            () => {

                const location =
                    marker.dataset.location;

                if (!selectedLocation)
                    return;


                let data = {

                    title:
                        "Soil Investigation Location",

                    municipality:
                        "Southern Leyte",

                    test:
                        "SPT",

                    capacity:
                        "Available",

                    date:
                        "Available"

                };


                if (
                    location.includes(
                        "Luyang"
                    )
                ) {

                    data = {

                        title:
                            "Barangay Luyang",

                        municipality:
                            "Sogod, Southern Leyte",

                        test:
                            "SPT Test",

                        capacity:
                            "250 kPa",

                        date:
                            "June 20, 2024"

                    };

                }


                else if (
                    location.includes(
                        "Guindapunan"
                    )
                ) {

                    data = {

                        title:
                            "Barangay Guindapunan",

                        municipality:
                            "Maasin City, Southern Leyte",

                        test:
                            "SPT Test",

                        capacity:
                            "180 kPa",

                        date:
                            "June 18, 2024"

                    };

                }


                else if (
                    location.includes(
                        "An-per"
                    )
                ) {

                    data = {

                        title:
                            "Barangay An-per",

                        municipality:
                            "San Juan, Southern Leyte",

                        test:
                            "SPT Test",

                        capacity:
                            "120 kPa",

                        date:
                            "June 15, 2024"

                    };

                }


                else if (
                    location.includes(
                        "Hibaga-an"
                    )
                ) {

                    data = {

                        title:
                            "Barangay Hibaga-an",

                        municipality:
                            "Hinunangan, Southern Leyte",

                        test:
                            "SPT Test",

                        capacity:
                            "210 kPa",

                        date:
                            "June 10, 2024"

                    };

                }


                else if (
                    location.includes(
                        "San Roque"
                    )
                ) {

                    data = {

                        title:
                            "Barangay San Roque",

                        municipality:
                            "Macrohon, Southern Leyte",

                        test:
                            "SPT Test",

                        capacity:
                            "95 kPa",

                        date:
                            "June 05, 2024"

                    };

                }


                selectedLocation.innerHTML = `

                    <div class="location-details">

                        <div class="location-title">

                            <i class="fa-solid fa-location-dot"></i>

                            <strong>
                                ${data.title}
                            </strong>

                        </div>

                        <p class="location-subtitle">
                            ${data.municipality}
                        </p>


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
                                ${data.capacity}
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
                                GIS Location
                            </strong>

                        </div>

                    </div>

                `;


                showMapToast(
                    data.title
                );

            }
        );

    });


    const mapSearch =
        document.querySelector(
            "#mapSearch"
        );

    const municipalityFilter =
        document.querySelector(
            "#municipalityFilter"
        );

    const capacityFilter =
        document.querySelector(
            "#capacityFilter"
        );


    const runMapSearch = () => {

        const searchValue =
            mapSearch?.value
                .trim()
                .toLowerCase();

        const municipality =
            municipalityFilter?.value;

        const capacity =
            capacityFilter?.value;


        if (
            searchValue ||
            municipality ||
            capacity
        ) {

            showMapToast(
                "Prototype filter applied"
            );

        }

    };


    mapSearch?.addEventListener(
        "keydown",
        event => {

            if (
                event.key === "Enter"
            ) {

                event.preventDefault();

                runMapSearch();

            }

        }
    );


    municipalityFilter?.addEventListener(
        "change",
        runMapSearch
    );

    capacityFilter?.addEventListener(
        "change",
        runMapSearch
    );


    const openSearch = () => {

        if (!searchModal)
            return;

        searchModal.classList.add(
            "open"
        );

        searchModal.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body.classList.add(
            "modal-open"
        );

        setTimeout(
            () => {

                searchInput?.focus();

            },
            100
        );
    };


    const closeSearch = () => {

        if (!searchModal)
            return;

        searchModal.classList.remove(
            "open"
        );

        searchModal.setAttribute(
            "aria-hidden",
            "true"
        );

        document.body.classList.remove(
            "modal-open"
        );

        if (searchResult) {

            searchResult.textContent =
                "";
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


    searchForm?.addEventListener(
        "submit",
        event => {

            event.preventDefault();

            const term =
                searchInput?.value
                    .trim();

            if (!term) {

                searchResult.textContent =
                    "Enter a municipality, barangay, or keyword.";

                return;
            }


            searchResult.innerHTML = `

                <i class="fa-solid fa-circle-info"></i>

                Prototype search:
                <strong>"${term}"</strong>

                would return matching soil
                records here.

            `;

        }
    );


    searchInput?.addEventListener(
        "search",
        () => {

            if (
                searchInput.value === ""
            ) {

                searchResult.textContent =
                    "";

            }

        }
    );


    document
        .querySelectorAll(
            'a[href^="#"]'
        )
        .forEach(link => {

            link.addEventListener(
                "click",
                event => {

                    const targetId =
                        link.getAttribute(
                            "href"
                        );

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

});