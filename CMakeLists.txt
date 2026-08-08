cmake_minimum_required(VERSION 3.16)
project(TraktRecommender LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

find_package(Qt6 REQUIRED COMPONENTS Widgets Network)

qt_standard_project_setup()

add_executable(TraktRecommender
    main.cpp
    mainwindow.cpp
    mainwindow.h
    traktclient.cpp
    traktclient.h
    statestore.cpp
    statestore.h
    showrecommender.cpp
    showrecommender.h
)

target_link_libraries(TraktRecommender PRIVATE
    Qt6::Widgets
    Qt6::Network
)
