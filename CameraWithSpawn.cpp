// Fill out your copyright notice in the Description page of Project Settings.

#include "MyTest.h"
#include "CameraWithSpawn.h"

const TCHAR* sceneName = _T("MyTest");

const TCHAR* objectList[NUM_OBJECT] = {
    // 1 個目
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

    // 5 個目
    _T("/Game/Assets/SK_EXO_Creature_Spider02_100.SK_EXO_Creature_Spider02_100"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_076.SK_EXO_Creature_Spider02_076"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_058.SK_EXO_Creature_Spider02_058"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_044.SK_EXO_Creature_Spider02_044"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_033.SK_EXO_Creature_Spider02_033"),
    _T("/Game/Assets/SK_EXO_Creature_Spider02_025.SK_EXO_Creature_Spider02_025"),

    _T("/Game/Assets/Forge_King_100.Forge_King_100"),
    _T("/Game/Assets/Forge_King_076.Forge_King_076"),
    _T("/Game/Assets/Forge_King_058.Forge_King_058"),
    _T("/Game/Assets/Forge_King_044.Forge_King_044"),
    _T("/Game/Assets/Forge_King_033.Forge_King_033"),
    _T("/Game/Assets/Forge_King_025.Forge_King_025"),

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

    // 10 個目
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

    // 12 個目
    _T("/Game/Assets/SK_CharM_Barbarous_100.SK_CharM_Barbarous_100"),
    _T("/Game/Assets/SK_CharM_Barbarous_076.SK_CharM_Barbarous_076"),
    _T("/Game/Assets/SK_CharM_Barbarous_058.SK_CharM_Barbarous_058"),
    _T("/Game/Assets/SK_CharM_Barbarous_044.SK_CharM_Barbarous_044"),
    _T("/Game/Assets/SK_CharM_Barbarous_033.SK_CharM_Barbarous_033"),
    _T("/Game/Assets/SK_CharM_Barbarous_025.SK_CharM_Barbarous_025"),
};

const TCHAR* materialList[NUM_MATERIAL] = {
    // 1 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Bear/Materials/M_Bear_Master.M_Bear_Master"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Elemental_Robot/Materials/M_Elemental_Robot_Master.M_Elemental_Robot_Master"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Chicken/Materials/M_EnemyChicken.M_EnemyChicken"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Clot_Worm/Materials/M_Clot_Worm.M_Clot_Worm"),
    // 5 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Creature_Spider/Materials/CharM_Creature_Spider.CharM_Creature_Spider"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Forge_King/Materials/Weapon.Weapon"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Frost_Giant/Materials/CharM_Frost_Giant.CharM_Frost_Giant"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Golem/Materials/CharM_Fire_Golem.CharM_Fire_Golem"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Great_Spider/Materials/CharM_Greater_Spider.CharM_Greater_Spider"),
    // 10 個目
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Greater_Spider_Boss/Materials/CharM_Greater_Spider_Boss.CharM_Greater_Spider_Boss"),
    _T("/Game/InfinityBladeAdversaries/Enemy/Enemy_Gruntling/Materials/CharM_Gruntling.CharM_Gruntling"),

    // 12 個目
    _T("/Game/InfinityBladeWarriors/Character/CompleteCharacters/Textures_Materials/CharM_Barbarous/M_Char_Barbrous.M_Char_Barbrous"),
};

const int materialIndices[NUM_MATERIAL] = {
    6,  12, 18, 24, 30, 36, 42, 48, 54, 60,
    66,
    72,
};

const float scaleList[NUM_MATERIAL] = {
    1.0f, 1.2f, 8.0f, 1.0f, 1.3f, 0.5f, 0.9f, 1.3f, 0.7f, 0.65f,
    2.0f,
    1.2f,
};

const float xRotateList[NUM_MATERIAL] = {
    0.0f, 90.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f, 0.0f,
    0.0f,
    0.0f,
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
        }
        else
        {
            UE_LOG(LogTemp, Warning, TEXT("output : %s %s"), materialList[i], L"マテリアルロードに失敗しました");
        }
    }

    materialIndex = 0;
    for (size_t i = 0; i < NUM_OBJECT; i++)
    {
        ConstructorHelpers::FObjectFinder<UStaticMesh>* pMeshAsset = new ConstructorHelpers::FObjectFinder<UStaticMesh>(objectList[i]);
        if (pMeshAsset->Succeeded())
        {
            if (materialIndices[materialIndex] == i)
                materialIndex++;

            mStaticMesh[i] = pMeshAsset->Object;
            mStaticMesh[i]->Materials[0] = mMaterial[materialIndex];
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
            mStaticMeshComponent->SetRelativeRotation(FRotator(xRotateList[materialIndex], currentYRotation, 0.0f));
        }
        else if (accumulateTime > timeToNextObject)
        {
            // ローテーションのバリエーションを行う
            if (currentYRotation >= 270.0f)
            {
                objectIndex++; remeshIndex++;
                int numObject = (materialIndex == 0) ? materialIndices[0] : materialIndices[materialIndex] - materialIndices[materialIndex-1];
                if (remeshIndex == numObject)
                {
                    // 次のジオメトリに飛ぶ
                    remeshIndex = 0;
                    variationIndex++;
                    materialIndex++;
                }
                else
                {
                    // 次のリメッシュに飛ぶ
                    variationIndex -= 3;
                }
                accumulateTime = 0.0f;
                currentYRotation = 0.0f;
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
            mStaticMeshComponent->SetRelativeRotation(FRotator(xRotateList[materialIndex], currentYRotation, 0.0f));

            screenShotFlag = true;
        }
        else if ( (accumulateTime > timeToNextObject / 2) && screenShotFlag )
        {
            TCHAR tmpchar[128];
            FString filename;
            // 比較を行うバリエーション / リメッシュのバリエーション
            _stprintf_s(tmpchar, sizeof(tmpchar), _T("%s_%.5d_%.1d.png"), sceneName, variationIndex, remeshIndex);
            filename.AppendChars(tmpchar, sizeof(tmpchar));

            // スクリーンショットの撮影
            FScreenshotRequest screenshot = FScreenshotRequest();
            screenshot.RequestScreenshot(filename, false, false);
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
    bStartCapture = screenShotFlag = true;
    variationIndex = materialIndex = remeshIndex = 0;
    currentYRotation = 0.0f;
}