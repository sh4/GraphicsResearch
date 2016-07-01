// Fill out your copyright notice in the Description page of Project Settings.

#include "MyTest.h"
#include "CameraWithSpawn.h"

const int startVariationIndex = 0;

const TCHAR* objectList[NUM_OBJECT] = {
    // 1 個目 (InfinityBladeAdversaries)
    _T("/Game/Assets/Enemy_Bear_100.Enemy_Bear_100"),
    _T("/Game/Assets/Enemy_Bear_076.Enemy_Bear_076"),
    _T("/Game/Assets/Enemy_Bear_058.Enemy_Bear_058"),
    _T("/Game/Assets/Enemy_Bear_044.Enemy_Bear_044"),
    _T("/Game/Assets/Enemy_Bear_033.Enemy_Bear_033"),
    _T("/Game/Assets/Enemy_Bear_025.Enemy_Bear_025"),

    _T("/Game/Assets/SK_Elemental_Boss_Robot_100.SK_Elemental_Boss_Robot_100"),
    _T("/Game/Assets/SK_Elemental_Boss_Robot_076.SK_Elemental_Boss_Robot_076"),
    _T("/Game/Assets/SK_Elemental_Boss_Robot_058.SK_Elemental_Boss_Robot_058"),
    _T("/Game/Assets/SK_Elemental_Boss_Robot_044.SK_Elemental_Boss_Robot_044"),
    _T("/Game/Assets/SK_Elemental_Boss_Robot_033.SK_Elemental_Boss_Robot_033"),
    _T("/Game/Assets/SK_Elemental_Boss_Robot_025.SK_Elemental_Boss_Robot_025"),

    _T("/Game/Assets/S_Survival_CA_Chicken_100.S_Survival_CA_Chicken_100"), 
    _T("/Game/Assets/S_Survival_CA_Chicken_076.S_Survival_CA_Chicken_076"),
    _T("/Game/Assets/S_Survival_CA_Chicken_058.S_Survival_CA_Chicken_058"),
    _T("/Game/Assets/S_Survival_CA_Chicken_044.S_Survival_CA_Chicken_044"),
    _T("/Game/Assets/S_Survival_CA_Chicken_033.S_Survival_CA_Chicken_033"),
    _T("/Game/Assets/S_Survival_CA_Chicken_025.S_Survival_CA_Chicken_025"),

    _T("/Game/Assets/SK_Enemy_Clot_Worm_100.SK_Enemy_Clot_Worm_100"),
    _T("/Game/Assets/SK_Enemy_Clot_Worm_076.SK_Enemy_Clot_Worm_076"),
    _T("/Game/Assets/SK_Enemy_Clot_Worm_058.SK_Enemy_Clot_Worm_058"),
    _T("/Game/Assets/SK_Enemy_Clot_Worm_044.SK_Enemy_Clot_Worm_044"),
    _T("/Game/Assets/SK_Enemy_Clot_Worm_033.SK_Enemy_Clot_Worm_033"),
    _T("/Game/Assets/SK_Enemy_Clot_Worm_025.SK_Enemy_Clot_Worm_025"),

    _T("/Game/Assets/SK_EXO_Creature_Spider02_100.SK_EXO_Creature_Spider02_100"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_076.SK_EXO_Creature_Spider02_076"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_058.SK_EXO_Creature_Spider02_058"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_044.SK_EXO_Creature_Spider02_044"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_033.SK_EXO_Creature_Spider02_033"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_025.SK_EXO_Creature_Spider02_025"),

    // 6 個目
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_100.SK_Enemy_FrostGiant_Captain_100"),
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_076.SK_Enemy_FrostGiant_Captain_076"),
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_058.SK_Enemy_FrostGiant_Captain_058"),
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_044.SK_Enemy_FrostGiant_Captain_044"),
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_033.SK_Enemy_FrostGiant_Captain_033"),
    _T("/Game/Assets/SK_Enemy_FrostGiant_Captain_025.SK_Enemy_FrostGiant_Captain_025"),
    
    _T("/Game/Assets/SK_Fire_Golem_100.SK_Fire_Golem_100"),
    _T("/Game/Assets/SK_Fire_Golem_076.SK_Fire_Golem_076"),
    _T("/Game/Assets/SK_Fire_Golem_058.SK_Fire_Golem_058"),
    _T("/Game/Assets/SK_Fire_Golem_044.SK_Fire_Golem_044"),
    _T("/Game/Assets/SK_Fire_Golem_033.SK_Fire_Golem_033"),
    _T("/Game/Assets/SK_Fire_Golem_025.SK_Fire_Golem_025"),

    _T("/Game/Assets/SK_Greater_Spider_100.SK_Greater_Spider_100"), 
    _T("/Game/Assets/SK_Greater_Spider_076.SK_Greater_Spider_076"),
    _T("/Game/Assets/SK_Greater_Spider_058.SK_Greater_Spider_058"),
    _T("/Game/Assets/SK_Greater_Spider_044.SK_Greater_Spider_044"),
    _T("/Game/Assets/SK_Greater_Spider_033.SK_Greater_Spider_033"),
    _T("/Game/Assets/SK_Greater_Spider_025.SK_Greater_Spider_025"),

    _T("/Game/Assets/SK_Greater_Spider_Boss_100.SK_Greater_Spider_Boss_100"),
    _T("/Game/Assets/SK_Greater_Spider_Boss_076.SK_Greater_Spider_Boss_076"),
    _T("/Game/Assets/SK_Greater_Spider_Boss_058.SK_Greater_Spider_Boss_058"),
    _T("/Game/Assets/SK_Greater_Spider_Boss_044.SK_Greater_Spider_Boss_044"),
    _T("/Game/Assets/SK_Greater_Spider_Boss_033.SK_Greater_Spider_Boss_033"),
    _T("/Game/Assets/SK_Greater_Spider_Boss_025.SK_Greater_Spider_Boss_025"),

    _T("/Game/Assets/SK_Exodus_Gruntling_100.SK_Exodus_Gruntling_100"),
    _T("/Game/Assets/SK_Exodus_Gruntling_076.SK_Exodus_Gruntling_076"),
    _T("/Game/Assets/SK_Exodus_Gruntling_058.SK_Exodus_Gruntling_058"),
    _T("/Game/Assets/SK_Exodus_Gruntling_044.SK_Exodus_Gruntling_044"),
    _T("/Game/Assets/SK_Exodus_Gruntling_033.SK_Exodus_Gruntling_033"),
    _T("/Game/Assets/SK_Exodus_Gruntling_025.SK_Exodus_Gruntling_025"),

    // 11 個目
    _T("/Game/Assets/SK_Gruntling_Avalanche_100.SK_Gruntling_Avalanche_100"),
    _T("/Game/Assets/SK_Gruntling_Avalanche_076.SK_Gruntling_Avalanche_076"),
    _T("/Game/Assets/SK_Gruntling_Avalanche_058.SK_Gruntling_Avalanche_058"),
    _T("/Game/Assets/SK_Gruntling_Avalanche_044.SK_Gruntling_Avalanche_044"),
    _T("/Game/Assets/SK_Gruntling_Avalanche_033.SK_Gruntling_Avalanche_033"),
    _T("/Game/Assets/SK_Gruntling_Avalanche_025.SK_Gruntling_Avalanche_025"),

    _T("/Game/Assets/SK_Gruntling_Glacer_100.SK_Gruntling_Glacer_100"),
    _T("/Game/Assets/SK_Gruntling_Glacer_076.SK_Gruntling_Glacer_076"),
    _T("/Game/Assets/SK_Gruntling_Glacer_058.SK_Gruntling_Glacer_058"),
    _T("/Game/Assets/SK_Gruntling_Glacer_044.SK_Gruntling_Glacer_044"),
    _T("/Game/Assets/SK_Gruntling_Glacer_033.SK_Gruntling_Glacer_033"),
    _T("/Game/Assets/SK_Gruntling_Glacer_025.SK_Gruntling_Glacer_025"),

    _T("/Game/Assets/SK_Gruntling_Guardian_100.SK_Gruntling_Guardian_100"),
    _T("/Game/Assets/SK_Gruntling_Guardian_076.SK_Gruntling_Guardian_076"),
    _T("/Game/Assets/SK_Gruntling_Guardian_058.SK_Gruntling_Guardian_058"),
    _T("/Game/Assets/SK_Gruntling_Guardian_044.SK_Gruntling_Guardian_044"),
    _T("/Game/Assets/SK_Gruntling_Guardian_033.SK_Gruntling_Guardian_033"),
    _T("/Game/Assets/SK_Gruntling_Guardian_025.SK_Gruntling_Guardian_025"),

    _T("/Game/Assets/SK_Gruntling_Scud_100.SK_Gruntling_Scud_100"),
    _T("/Game/Assets/SK_Gruntling_Scud_076.SK_Gruntling_Scud_076"),
    _T("/Game/Assets/SK_Gruntling_Scud_058.SK_Gruntling_Scud_058"),
    _T("/Game/Assets/SK_Gruntling_Scud_044.SK_Gruntling_Scud_044"),
    _T("/Game/Assets/SK_Gruntling_Scud_033.SK_Gruntling_Scud_033"),
    _T("/Game/Assets/SK_Gruntling_Scud_025.SK_Gruntling_Scud_025"),

    _T("/Game/Assets/SK_Master_Grunt_100.SK_Master_Grunt_100"),
    _T("/Game/Assets/SK_Master_Grunt_076.SK_Master_Grunt_076"),
    _T("/Game/Assets/SK_Master_Grunt_058.SK_Master_Grunt_058"),
    _T("/Game/Assets/SK_Master_Grunt_044.SK_Master_Grunt_044"),
    _T("/Game/Assets/SK_Master_Grunt_033.SK_Master_Grunt_033"),
    _T("/Game/Assets/SK_Master_Grunt_025.SK_Master_Grunt_025"),

    // 16 個目
    _T("/Game/Assets/SK_Robo_Golem_100.SK_Robo_Golem_100"),
    _T("/Game/Assets/SK_Robo_Golem_076.SK_Robo_Golem_076"),
    _T("/Game/Assets/SK_Robo_Golem_058.SK_Robo_Golem_058"),
    _T("/Game/Assets/SK_Robo_Golem_044.SK_Robo_Golem_044"),
    _T("/Game/Assets/SK_Robo_Golem_033.SK_Robo_Golem_033"),
    _T("/Game/Assets/SK_Robo_Golem_025.SK_Robo_Golem_025"),
        
    _T("/Game/Assets/SK_Spiderling_100.SK_Spiderling_100"),
    _T("/Game/Assets/SK_Spiderling_076.SK_Spiderling_076"),
    _T("/Game/Assets/SK_Spiderling_058.SK_Spiderling_058"),
    _T("/Game/Assets/SK_Spiderling_044.SK_Spiderling_044"),
    _T("/Game/Assets/SK_Spiderling_033.SK_Spiderling_033"),
    _T("/Game/Assets/SK_Spiderling_025.SK_Spiderling_025"),
        
    _T("/Game/Assets/Enemy_Task_Master_100.Enemy_Task_Master_100"),
    _T("/Game/Assets/Enemy_Task_Master_076.Enemy_Task_Master_076"),
    _T("/Game/Assets/Enemy_Task_Master_058.Enemy_Task_Master_058"),
    _T("/Game/Assets/Enemy_Task_Master_044.Enemy_Task_Master_044"),
    _T("/Game/Assets/Enemy_Task_Master_033.Enemy_Task_Master_033"),
    _T("/Game/Assets/Enemy_Task_Master_025.Enemy_Task_Master_025"),
        
    _T("/Game/Assets/SK_Troll_Poison_100.SK_Troll_Poison_100"),
    _T("/Game/Assets/SK_Troll_Poison_076.SK_Troll_Poison_076"),
    _T("/Game/Assets/SK_Troll_Poison_058.SK_Troll_Poison_058"),
    _T("/Game/Assets/SK_Troll_Poison_044.SK_Troll_Poison_044"),
    _T("/Game/Assets/SK_Troll_Poison_033.SK_Troll_Poison_033"),
    _T("/Game/Assets/SK_Troll_Poison_025.SK_Troll_Poison_025"),

    _T("/Game/Assets/SK_Enemy_Wolf_Armored_100.SK_Enemy_Wolf_Armored_100"),
    _T("/Game/Assets/SK_Enemy_Wolf_Armored_076.SK_Enemy_Wolf_Armored_076"),
    _T("/Game/Assets/SK_Enemy_Wolf_Armored_058.SK_Enemy_Wolf_Armored_058"),
    _T("/Game/Assets/SK_Enemy_Wolf_Armored_044.SK_Enemy_Wolf_Armored_044"),
    _T("/Game/Assets/SK_Enemy_Wolf_Armored_033.SK_Enemy_Wolf_Armored_033"),
    _T("/Game/Assets/SK_Enemy_Wolf_Armored_025.SK_Enemy_Wolf_Armored_025"),
        /*
    // 21 個目 (InfinityBladeWarriors) 
    _T("/Game/Assets/SK_CharM_Barbarous_100.SK_CharM_Barbarous_100"),
    _T("/Game/Assets/SK_CharM_Barbarous_076.SK_CharM_Barbarous_076"),
    _T("/Game/Assets/SK_CharM_Barbarous_058.SK_CharM_Barbarous_058"),
    _T("/Game/Assets/SK_CharM_Barbarous_044.SK_CharM_Barbarous_044"),
    _T("/Game/Assets/SK_CharM_Barbarous_033.SK_CharM_Barbarous_033"),
    _T("/Game/Assets/SK_CharM_Barbarous_025.SK_CharM_Barbarous_025"),
        
    _T("/Game/Assets/sk_CharM_Base_100.sk_CharM_Base_100"),
    _T("/Game/Assets/sk_CharM_Base_076.sk_CharM_Base_076"),
    _T("/Game/Assets/sk_CharM_Base_058.sk_CharM_Base_058"),
    _T("/Game/Assets/sk_CharM_Base_044.sk_CharM_Base_044"),
    _T("/Game/Assets/sk_CharM_Base_033.sk_CharM_Base_033"),
    _T("/Game/Assets/sk_CharM_Base_025.sk_CharM_Base_025"),
        
    _T("/Game/Assets/SK_CharM_Bladed_100.SK_CharM_Bladed_100"),
    _T("/Game/Assets/SK_CharM_Bladed_076.SK_CharM_Bladed_076"),
    _T("/Game/Assets/SK_CharM_Bladed_058.SK_CharM_Bladed_058"),
    _T("/Game/Assets/SK_CharM_Bladed_044.SK_CharM_Bladed_044"),
    _T("/Game/Assets/SK_CharM_Bladed_033.SK_CharM_Bladed_033"),
    _T("/Game/Assets/SK_CharM_Bladed_025.SK_CharM_Bladed_025"),
        
    _T("/Game/Assets/SK_CharM_Cardboard_100.SK_CharM_Cardboard_100"),
    _T("/Game/Assets/SK_CharM_Cardboard_076.SK_CharM_Cardboard_076"),
    _T("/Game/Assets/SK_CharM_Cardboard_058.SK_CharM_Cardboard_058"),
    _T("/Game/Assets/SK_CharM_Cardboard_044.SK_CharM_Cardboard_044"),
    _T("/Game/Assets/SK_CharM_Cardboard_033.SK_CharM_Cardboard_033"),
    _T("/Game/Assets/SK_CharM_Cardboard_025.SK_CharM_Cardboard_025"),
        
    _T("/Game/Assets/SK_CharM_Forge_100.SK_CharM_Forge_100"),
    _T("/Game/Assets/SK_CharM_Forge_076.SK_CharM_Forge_076"),
    _T("/Game/Assets/SK_CharM_Forge_058.SK_CharM_Forge_058"),
    _T("/Game/Assets/SK_CharM_Forge_044.SK_CharM_Forge_044"),
    _T("/Game/Assets/SK_CharM_Forge_033.SK_CharM_Forge_033"),
    _T("/Game/Assets/SK_CharM_Forge_025.SK_CharM_Forge_025"),

     // 26 個目
    _T("/Game/Assets/SK_CharM_FrostGiant_100.SK_CharM_FrostGiant_100"),
    _T("/Game/Assets/SK_CharM_FrostGiant_076.SK_CharM_FrostGiant_076"),
    _T("/Game/Assets/SK_CharM_FrostGiant_058.SK_CharM_FrostGiant_058"),
    _T("/Game/Assets/SK_CharM_FrostGiant_044.SK_CharM_FrostGiant_044"),
    _T("/Game/Assets/SK_CharM_FrostGiant_033.SK_CharM_FrostGiant_033"),
    _T("/Game/Assets/SK_CharM_FrostGiant_025.SK_CharM_FrostGiant_025"),
        
    _T("/Game/Assets/SK_CharM_Golden_100.SK_CharM_Golden_100"),
    _T("/Game/Assets/SK_CharM_Golden_076.SK_CharM_Golden_076"),
    _T("/Game/Assets/SK_CharM_Golden_058.SK_CharM_Golden_058"),
    _T("/Game/Assets/SK_CharM_Golden_044.SK_CharM_Golden_044"),
    _T("/Game/Assets/SK_CharM_Golden_033.SK_CharM_Golden_033"),
    _T("/Game/Assets/SK_CharM_Golden_025.SK_CharM_Golden_025"),
        
    _T("/Game/Assets/SK_CharM_Natural_100.SK_CharM_Natural_100"),
    _T("/Game/Assets/SK_CharM_Natural_076.SK_CharM_Natural_076"),
    _T("/Game/Assets/SK_CharM_Natural_058.SK_CharM_Natural_058"),
    _T("/Game/Assets/SK_CharM_Natural_044.SK_CharM_Natural_044"),
    _T("/Game/Assets/SK_CharM_Natural_033.SK_CharM_Natural_033"),
    _T("/Game/Assets/SK_CharM_Natural_025.SK_CharM_Natural_025"),
        
    _T("/Game/Assets/SK_CharM_Pit_100.SK_CharM_Pit_100"),
    _T("/Game/Assets/SK_CharM_Pit_076.SK_CharM_Pit_076"),
    _T("/Game/Assets/SK_CharM_Pit_058.SK_CharM_Pit_058"),
    _T("/Game/Assets/SK_CharM_Pit_044.SK_CharM_Pit_044"),
    _T("/Game/Assets/SK_CharM_Pit_033.SK_CharM_Pit_033"),
    _T("/Game/Assets/SK_CharM_Pit_025.SK_CharM_Pit_025"),
        
    _T("/Game/Assets/SK_CharM_Ragged0_100.SK_CharM_Ragged0_100"),
    _T("/Game/Assets/SK_CharM_Ragged0_076.SK_CharM_Ragged0_076"),
    _T("/Game/Assets/SK_CharM_Ragged0_058.SK_CharM_Ragged0_058"),
    _T("/Game/Assets/SK_CharM_Ragged0_044.SK_CharM_Ragged0_044"),
    _T("/Game/Assets/SK_CharM_Ragged0_033.SK_CharM_Ragged0_033"),
    _T("/Game/Assets/SK_CharM_Ragged0_025.SK_CharM_Ragged0_025"),
        
     // 31 個目
    _T("/Game/Assets/SK_CharM_RaggedElite_100.SK_CharM_RaggedElite_100"),
    _T("/Game/Assets/SK_CharM_RaggedElite_076.SK_CharM_RaggedElite_076"),
    _T("/Game/Assets/SK_CharM_RaggedElite_058.SK_CharM_RaggedElite_058"),
    _T("/Game/Assets/SK_CharM_RaggedElite_044.SK_CharM_RaggedElite_044"),
    _T("/Game/Assets/SK_CharM_RaggedElite_033.SK_CharM_RaggedElite_033"),
    _T("/Game/Assets/SK_CharM_RaggedElite_025.SK_CharM_RaggedElite_025"),
        
    _T("/Game/Assets/SK_CharM_Ram_100.SK_CharM_Ram_100"),
    _T("/Game/Assets/SK_CharM_Ram_076.SK_CharM_Ram_076"),
    _T("/Game/Assets/SK_CharM_Ram_058.SK_CharM_Ram_058"),
    _T("/Game/Assets/SK_CharM_Ram_044.SK_CharM_Ram_044"),
    _T("/Game/Assets/SK_CharM_Ram_033.SK_CharM_Ram_033"),
    _T("/Game/Assets/SK_CharM_Ram_025.SK_CharM_Ram_025"),
        
    _T("/Game/Assets/SK_CharM_Robo_100.SK_CharM_Robo_100"),
    _T("/Game/Assets/SK_CharM_Robo_076.SK_CharM_Robo_076"),
    _T("/Game/Assets/SK_CharM_Robo_058.SK_CharM_Robo_058"),
    _T("/Game/Assets/SK_CharM_Robo_044.SK_CharM_Robo_044"),
    _T("/Game/Assets/SK_CharM_Robo_033.SK_CharM_Robo_033"),
    _T("/Game/Assets/SK_CharM_Robo_025.SK_CharM_Robo_025"),
        
    _T("/Game/Assets/SK_CharM_Shell_100.SK_CharM_Shell_100"),
    _T("/Game/Assets/SK_CharM_Shell_076.SK_CharM_Shell_076"),
    _T("/Game/Assets/SK_CharM_Shell_058.SK_CharM_Shell_058"),
    _T("/Game/Assets/SK_CharM_Shell_044.SK_CharM_Shell_044"),
    _T("/Game/Assets/SK_CharM_Shell_033.SK_CharM_Shell_033"),
    _T("/Game/Assets/SK_CharM_Shell_025.SK_CharM_Shell_025"),
 
    _T("/Game/Assets/SK_CharM_solid_100.SK_CharM_solid_100"),
    _T("/Game/Assets/SK_CharM_solid_076.SK_CharM_solid_076"),
    _T("/Game/Assets/SK_CharM_solid_058.SK_CharM_solid_058"),
    _T("/Game/Assets/SK_CharM_solid_044.SK_CharM_solid_044"),
    _T("/Game/Assets/SK_CharM_solid_033.SK_CharM_solid_033"),
    _T("/Game/Assets/SK_CharM_solid_025.SK_CharM_solid_025"),

     // 36 個目
    _T("/Game/Assets/SK_CharM_Standard_100.SK_CharM_Standard_100"),
    _T("/Game/Assets/SK_CharM_Standard_076.SK_CharM_Standard_076"),
    _T("/Game/Assets/SK_CharM_Standard_058.SK_CharM_Standard_058"),
    _T("/Game/Assets/SK_CharM_Standard_044.SK_CharM_Standard_044"),
    _T("/Game/Assets/SK_CharM_Standard_033.SK_CharM_Standard_033"),
    _T("/Game/Assets/SK_CharM_Standard_025.SK_CharM_Standard_025"),
        
    _T("/Game/Assets/SK_CharM_Tusk_100.SK_CharM_Tusk_100"),
    _T("/Game/Assets/SK_CharM_Tusk_076.SK_CharM_Tusk_076"),
    _T("/Game/Assets/SK_CharM_Tusk_058.SK_CharM_Tusk_058"),
    _T("/Game/Assets/SK_CharM_Tusk_044.SK_CharM_Tusk_044"),
    _T("/Game/Assets/SK_CharM_Tusk_033.SK_CharM_Tusk_033"),
    _T("/Game/Assets/SK_CharM_Tusk_025.SK_CharM_Tusk_025"),
        */
};

const TCHAR* materialList[NUM_MATERIAL] = {
    // 1 個目 (InfinityBladeAdversaries)
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Bear/Materials/M_Bear_Master.M_Bear_Master"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Elemental_Robot/Materials/M_Elemental_Robot_Master.M_Elemental_Robot_Master"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Chicken/Materials/M_EnemyChicken.M_EnemyChicken"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Clot_Worm/Materials/M_Clot_Worm.M_Clot_Worm"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Creature_Spider/Materials/CharM_Creature_Spider.CharM_Creature_Spider"),
    // 6 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Frost_Giant/Materials/CharM_Frost_Giant.CharM_Frost_Giant"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Golem/Materials/CharM_Fire_Golem.CharM_Fire_Golem"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Great_Spider/Materials/CharM_Greater_Spider.CharM_Greater_Spider"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Greater_Spider_Boss/Materials/CharM_Greater_Spider_Boss.CharM_Greater_Spider_Boss"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling/Materials/CharM_Gruntling.CharM_Gruntling"),
    // 11 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling_Avalanche/Materials/CharM_Gruntling_Avalanche.CharM_Gruntling_Avalanche"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling_Glacer/Materials/CharM_Gruntling_Glacer.CharM_Gruntling_Glacer"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling_Guardian/Materials/CharM_Gruntling_Guardian.CharM_Gruntling_Guardian"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling_Scud/Materials/CharM_Gruntling_Scud_C.CharM_Gruntling_Scud_C"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Master_Grunt/Materials/CharM_Master_Grunt.CharM_Master_Grunt"),
    // 16 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Robo_Golem/Materials/CharM_Robo_Golem.CharM_Robo_Golem"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Spiderling/Materials/CharM_Spiderling.CharM_Spiderling"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Task_Master/Materials/M_Enemy_Task_Master.M_Enemy_Task_Master"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Troll/Materials/CharM_Troll.CharM_Troll"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Wolf/Materials/CharM_Enemy_Wolf.CharM_Enemy_Wolf"),

    // 21 個目 (InfinityBladeWarriors) 
//    _T("/Game/InfinityBladeWarriors/Character/CompleteCharacters/Textures_Materials/CharM_Barbarous/M_Char_Barbrous.M_Char_Barbrous"),
};

const float scaleList[NUM_MATERIAL] = {
    1.0f, 1.2f, 8.0f, 1.0f, 1.3f, 0.7f, 1.3f, 0.9f, 0.7f, 1.9f,
    1.8f, 1.9f, 2.0f, 2.0f, 1.1f, 0.6f, 1.2f, 1.0f, 0.9f, 1.3f,
};

const float xRotateList[NUM_MATERIAL] = {
    0.0f, -90.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f,
    0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f,
};

// Sets default values
ACameraWithSpawn::ACameraWithSpawn(const FObjectInitializer& ObjectInitializer)
{
    // Set this pawn to call Tick() every frame.  You can turn this off to improve performance if you don't need it.
    PrimaryActorTick.bCanEverTick = true;

    // Set this pawn to be controlled by the lowest-numbered player (このポーンが最小値のプレイヤーで制御されるように設定)
    AutoPossessPlayer = EAutoReceiveInput::Player0;

    // ダミーキャラクターを置く
    RootComponent = CreateDefaultSubobject<USceneComponent>(TEXT("RootComponent"));
    // Create a dummy root component we can attach things to.(親子付け可能なダミーのルートコンポーネントを作成)
    UCameraComponent* OurCamera = CreateDefaultSubobject<UCameraComponent>(TEXT("OurCamera"));

    // Attach our camera and visible object to our root component. (カメラと可視オブジェクトをルートコンポーネントに親子付け。カメラをオフセットして回転)
    OurCamera->AttachTo(RootComponent);
    OurCamera->SetRelativeLocation(FVector(-350.0f, 0.0f, 100.0f));
    OurCamera->SetRelativeRotation(FRotator(0.0f, 0.0f, 0.0f));

    for (size_t i = 0; i < NUM_MATERIAL; i++)
    {
        ConstructorHelpers::FObjectFinder<UMaterial>* pMaterialAsset = new ConstructorHelpers::FObjectFinder<UMaterial>(materialList[i]);
        if (pMaterialAsset->Succeeded())
        {
            mMaterial[i] = pMaterialAsset->Object;
            UE_LOG(LogTemp, Warning, TEXT("output : %s %s"), materialList[i], L"マテリアルロードに成功しました");
        }
        else
        {
            UE_LOG(LogTemp, Warning, TEXT("output : %s %s"), materialList[i], L"マテリアルロードに失敗しました");
        }
    }

    materialIndex = variationIndex = 0;
    for (size_t i = 0; i < NUM_OBJECT; i++)
    {
        ConstructorHelpers::FObjectFinder<UStaticMesh>* pMeshAsset = new ConstructorHelpers::FObjectFinder<UStaticMesh>(objectList[i]);
        if (pMeshAsset->Succeeded())
        {
            mStaticMesh[i] = pMeshAsset->Object;
            mStaticMesh[i]->Materials[0] = mMaterial[i / NUM_LOD];
            UE_LOG(LogTemp, Warning, TEXT("output : %s %s"), objectList[i], L"メッシュロードに成功しました");
        }
        else
        {
            UE_LOG(LogTemp, Warning, TEXT("output : %s %s"), objectList[i], L"メッシュロードに失敗しました");
        }
    }

    mStaticMeshComponent = ObjectInitializer.CreateDefaultSubobject<UStaticMeshComponent>(this, TEXT("StaticMeshName"));
    // オブジェクトが生成出来ました
    if (mStaticMeshComponent)
    {
        mStaticMeshComponent->SetStaticMesh(mStaticMesh[0]);
        mStaticMeshComponent->AttachTo(RootComponent);
    }
}

// Called when the game starts or when spawned
void ACameraWithSpawn::BeginPlay()
{
    Super::BeginPlay();

}

// Called every frame
void ACameraWithSpawn::Tick(float DeltaTime)
{
    Super::Tick(DeltaTime);

    if (bStartCapture)
    {
        const float timeToNextObject = 0.05f;
        if (objectIndex == NUM_OBJECT)
        {
            // キャプチャを終了する
            bStartCapture = false;
            accumulateTime = 0;
            objectIndex = remeshIndex = materialIndex = 0;
            mStaticMeshComponent->SetStaticMesh(mStaticMesh[0]);
            mStaticMeshComponent->SetRelativeScale3D(FVector(scaleList[materialIndex]));
            mStaticMeshComponent->SetRelativeRotation(FRotator(0.0f, currentYRotation, 0.0f));
        }
        else if (accumulateTime > timeToNextObject)
        {
            if (currentYRotation > 270.0f)
            {
                objectIndex++; remeshIndex++;
                if (remeshIndex == NUM_LOD)
                {
                    // 次のジオメトリに飛ぶ
                    remeshIndex = 0;
                    variationIndex++;
                    materialIndex++;
                    currentYRotation = FMath::RandRange(0.1f, 89.f);
                }
                else
                {
                    // 次のリメッシュに飛ぶ
                    variationIndex -= 3;
                    currentYRotation -= 270.0f;
                }
                accumulateTime = 0.0f;
                UE_LOG(LogTemp, Warning, TEXT("object index is %d"), objectIndex);
                mStaticMeshComponent->SetStaticMesh(mStaticMesh[objectIndex]);
            }
            else
            {
                // ローテーションのバリエーションを行う
                variationIndex++;
                accumulateTime = 0.0f;
                currentYRotation += 90.0f;
            }
            mStaticMeshComponent->SetRelativeScale3D(FVector(scaleList[materialIndex]));
            mStaticMeshComponent->SetRelativeRotation(FRotator(0.0f, currentYRotation, xRotateList[materialIndex]));

            screenShotFlag = true;
        }
        else if ( (accumulateTime > timeToNextObject / 2) && screenShotFlag )
        {
            TCHAR tmpchar[128];
            FString filename;
            // 比較を行うバリエーション / リメッシュのバリエーション
            if (!firstObjectFlag)
            {
                _stprintf_s(tmpchar, sizeof(tmpchar), _T("%.7d_%.1d.png"), startVariationIndex + variationIndex, remeshIndex);
                filename.AppendChars(tmpchar, sizeof(tmpchar));

                // スクリーンショットの撮影
                FScreenshotRequest screenshot = FScreenshotRequest();
                screenshot.RequestScreenshot(filename, false, false);
            }
            else {
                firstObjectFlag = false;
                variationIndex--;
            }
            screenShotFlag = false;
        }
        else {
            accumulateTime += DeltaTime;
        }
    }
    // Handle growing and shrinking based on our "Grow" action (Grow アクションに基づいて拡大と縮小を処理)
    else
    {
        FRotator NewRotation = GetActorRotation();
        NewRotation.Yaw += CameraInput.X;
        SetActorRotation(NewRotation);

        if (!MovementInput.IsZero())
        {
            //Scale our movement input axis values by 100 units per second (移動入力軸の値を 1 秒あたり 100 単位でスケーリング)
            MovementInput = MovementInput.SafeNormal() * 100.0f;
            FVector NewLocation = GetActorLocation();
            NewLocation += GetActorForwardVector() * MovementInput.X * DeltaTime * 5.0f;
            NewLocation += GetActorRightVector() * MovementInput.Y * DeltaTime * 5.0f;
            SetActorLocation(NewLocation);
        }
    }
}

// Called to bind functionality to input
void ACameraWithSpawn::SetupPlayerInputComponent(class UInputComponent* InputComponent)
{
    Super::SetupPlayerInputComponent(InputComponent);

    //Hook up every-frame handling for our four axes (4 つの軸に各フレーム処理を接続)
    InputComponent->BindAxis("MoveForward", this, &ACameraWithSpawn::MoveForward);
    InputComponent->BindAxis("MoveRight", this, &ACameraWithSpawn::MoveRight);
    InputComponent->BindAxis("CameraPitch", this, &ACameraWithSpawn::PitchCamera);
    InputComponent->BindAxis("CameraYaw", this, &ACameraWithSpawn::YawCamera);

    // Respond when our "Grow" key is pressed or released. (StartCapture キーがリリースされた時に反応)
    InputComponent->BindAction("StartCapture", IE_Pressed, this, &ACameraWithSpawn::StartCapture);
}

//Input functions (入力関数)
void ACameraWithSpawn::MoveForward(float AxisValue)
{
    MovementInput.X = FMath::Clamp<float>(AxisValue, -1.0f, 1.0f);
}

void ACameraWithSpawn::MoveRight(float AxisValue)
{
    MovementInput.Y = FMath::Clamp<float>(AxisValue, -1.0f, 1.0f);
}

void ACameraWithSpawn::PitchCamera(float AxisValue)
{
    CameraInput.Y = AxisValue;
}

void ACameraWithSpawn::YawCamera(float AxisValue)
{
    CameraInput.X = AxisValue;
}

void ACameraWithSpawn::StartCapture()
{
    UE_LOG(LogTemp, Warning, TEXT("start capture"));
    accumulateTime = 0;
    objectIndex = 0;
    bStartCapture = screenShotFlag = firstObjectFlag = true;
    materialIndex = remeshIndex = 0;
    currentYRotation = 0.1f;
}